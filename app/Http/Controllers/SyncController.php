<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

class SyncController extends Controller
{
    /**
     * All logic of synchronize two databases      *
     * @throws PublicException
     * @author Ali Basha
     */
    public function index()
    {
        $reposnse = '';
        //check db connection for local db
        $pdo = DB::connection()->getPdo();
        if ($pdo) {
            $reposnse .= "Connected successfully to local database " . DB::connection()->getDatabaseName() . '<br>';
        } else {
            $reposnse .= "You are not connected to local database <br>";
        }
        //check db connection for remote db
        $pdo = DB::connection('mysql_2')->getPdo();
        if ($pdo) {
            $reposnse .= "Connected successfully to remote database " . DB::connection('mysql_2')->getDatabaseName() . '<br>';
        } else {
            $reposnse .= "You are not connected to remote database <br>";
        }
        echo $reposnse;
        //get all unsynced rows from audits table 
        $operations = DB::connection('mysql')->select(DB::raw("select * from audits where `synced` = 0;"));
        $array_of_operations = (array)$operations;
        //looping through all unsynced rows
        foreach ($array_of_operations as $operate) {
            //check if the event is insert 
            if ($operate->event == 'created') {
                //get new_values (which is column from audits table) as json
                $new_values_as_json = json_decode($operate->new_values, true);
                //initialise two arrays to split values and columns to work with raw query
                $columns = [];
                $values = [];
                //loop through new_values
                foreach ($new_values_as_json as $key => $value) {
                    if ($key != 'id') {
                        array_push($columns, $key);
                        array_push($values, $value);
                    }
                }
                //add created_at field cuz auditable package don't add timestamps to new or old values 
                array_push($values, $operate->created_at);
                array_push($columns, 'created_at');
                //clean values and columns
                $clean_values = $this->ResolveArrayToFitQuery($values, false);
                $clean_columns = $this->ResolveArrayToFitQuery($columns, true);
                //collect raw query as string.
                $insert_new_record_query = 'INSERT INTO `' . $operate->auditable_table . '` ' . $clean_columns . ' VALUES ' . $clean_values;
                // run query of inserting new record on remote db
                DB::connection('mysql_2')->select(DB::raw($insert_new_record_query));
                /** now we must insert the record of local audits table with 'synced'==1  */
                //reset columns,values
                $columns = [];
                $values = [];
                //parse $oprate (which is the row of audits table that we are working on) as array
                $operate_as_array =  (array)$operate;
                //looping throught $operate_as_array 
                foreach ($operate_as_array as $key => $value) {
                    //check that key not id and not synced --here we ignore id to avoid conflicts while inserting into db from multiple locations
                    if ($key != 'id' and $key != 'synced') {
                        array_push($columns, $key);
                        array_push($values, $value);
                    }
                    //check if key is synced set value to 1 (to store it on remote db as synced) 
                    if ($key == 'synced') {
                        array_push($columns, $key);
                        array_push($values, 1);
                    }
                } //end of  operate_as_array foreach
                //clean values and columns
                $value = $this->ResolveArrayToFitQuery($values, false);
                $column = $this->ResolveArrayToFitQuery($columns, true);
                // print($value);
                $insert_current_audits_row_query = 'INSERT INTO `audits` ' . $column . ' VALUES ' . $value;
                //echo  $query;
                DB::connection('mysql_2')->select(DB::raw($insert_current_audits_row_query));
                DB::connection('mysql')->select(DB::raw('UPDATE `audits` SET `synced` = 1 WHERE `audits`.`id` =' . $operate->id));
            } //end if(create)
            elseif ($operate->event == 'updated') {
                //coming soon
            } //end of if updated
            elseif ($operate->event == 'deleted') {
                //coming soon
            } //end of if deleted
        } //end of main foreach
    } //end of index function
    /**
     * basic clean array to fit raw query     *
     * @param  array  $columns
     * @param  boolean  $is_column
     *
     * @return array
     * @throws PublicException
     * @author Ali Basha
     */
    private function ResolveArrayToFitQuery($columns, $is_column)
    {
        $column = json_encode($columns, true);
        $column = str_replace('[', '(', $column);
        $column = str_replace(']', ')', $column);
        if ($is_column) {
            $column = str_replace('"', '`', $column);
            return $column;
        }
        return $column;
    }
}
