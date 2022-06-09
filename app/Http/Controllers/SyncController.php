<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;
use PDOException;

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
        $can_sync = true;
        //check db connection for local db

        // if ($pdo) {
        //     $reposnse .= "Connected successfully to local database " . DB::connection()->getDatabaseName() . '<br>';
        // } else {
        //     $can_sync = false;
        //     $reposnse .= "You are not connected to local database <br>";
        // }
        try {
            $reposnse .= "Connected successfully to local database " . DB::connection()->getDatabaseName() . '<br>';
            DB::connection()->getPdo();
        } catch (\PDOException $e) {
            $reposnse .= $e->getMessage();
        }
        //check db connection for remote db


        try {
            $reposnse .= "Connected successfully to remote database " . DB::connection('mysql_2')->getDatabaseName() . '<br>';
            DB::connection('mysql_2')->getPdo();
        } catch (\PDOException $e) {
            $can_sync = false;
            $reposnse .= "You are not connected to remote database <br>" . $e->getMessage();
        }

        if ($can_sync) {
            //run sync source to destination
            $this->Sync('mysql', 'mysql_2');
            //reverse the process to get the changes from remote to local
            $this->Sync('mysql_2', 'mysql');
        } //end on sync process
        else {
            $reposnse .= "Sorry We Can not do synchronizing process check your connection.";
        }
        echo $reposnse;
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
    /**
     * basic sync two databaese baesd on audits table     *
     * @param  string  $local connection name 
     * @param  string  $remote connection name
     *
     *
     * @throws PublicException
     * @author Ali Basha
     */
    private function Sync($local_db_connection, $remote_db_connection)
    {
        //get all unsynced rows from audits table 
        $operations = DB::connection($local_db_connection)->select(DB::raw("select * from audits where `synced` = 0;"));
        $array_of_operations = (array)$operations;
        //looping through all unsynced rows
        foreach ($array_of_operations as $operate) {
            //get new_values (which is column from audits table) as json
            $new_values_as_json = json_decode($operate->new_values, true);
            //get old_values (which is column from audits table) as json
            $old_values_as_json = json_decode($operate->old_values, true);
            //initialise two arrays to split values and columns to work with raw query
            $columns = [];
            $old_values = [];
            $new_values = [];
            if ($new_values_as_json != null) {
                //loop through new_values
                foreach ($new_values_as_json as $key => $value) {
                    if ($key != 'id') {
                        array_push($columns, $key);
                        array_push($new_values, $value);
                    }
                }
            }
            //check if old_values not null
            if ($old_values_as_json != null) {
                //loop through old_values
                foreach ($old_values_as_json as $key => $value) {
                    if ($key != 'id') {
                        array_push($old_values, $value);
                    }
                }
            }
            //add created_at field cuz auditable package don't add timestamps to new or old values 
            array_push($new_values, $operate->created_at);
            array_push($old_values, $operate->created_at);
            array_push($columns, 'created_at');
            //clean values and columns
            $clean_new_values = $this->ResolveArrayToFitQuery($new_values, false);
            $clean_columns = $this->ResolveArrayToFitQuery($columns, true);
            //check if the event is insert 
            if ($operate->event == 'created') {
                //collect raw query as string.
                $insert_new_record_query = 'INSERT INTO `' . $operate->auditable_table . '` ' . $clean_columns . ' VALUES ' . $clean_new_values;
                // run query of inserting new record on remote db
                DB::connection($remote_db_connection)->select(DB::raw($insert_new_record_query));
                /** now we must insert the record of local audits table with 'synced'==1  */
                //reset columns,values
                $columns = [];
                $values = [];
            } //end if(create)
            elseif ($operate->event == 'updated') {
                //coming soon
            } //end of if update
            elseif ($operate->event == 'deleted') {
                //coming soon
            } //end of if delete
            //init audit colums and values
            $audit_columns = [];
            $audit_values = [];
            //parse $oprate (which is the row of audits table that we are working on) as array
            $operate_as_array =  (array)$operate;
            //looping throught $operate_as_array 
            foreach ($operate_as_array as $key => $value) {
                //check that key not id and not synced --here we ignore id to avoid conflicts while inserting into db from multiple locations
                if ($key != 'id' and $key != 'synced') {
                    array_push($audit_columns, $key);
                    array_push($audit_values, $value);
                }
                //check if key is synced set value to 1 (to store it on remote db as synced) 
                if ($key == 'synced') {
                    array_push($audit_columns, $key);
                    array_push($audit_values, 1);
                }
            } //end of  operate_as_array foreach
            //clean audit values and columns
            $audit_values = $this->ResolveArrayToFitQuery($audit_values, false);
            $audit_columns = $this->ResolveArrayToFitQuery($audit_columns, true);
            //collect raw query as string to insert current audits row
            $insert_current_audits_row_query = 'INSERT INTO `audits` ' . $audit_columns . ' VALUES ' . $audit_values;
            //run query of inserting audits record on remote db
            DB::connection($remote_db_connection)->select(DB::raw($insert_current_audits_row_query));
            //run query of inserting audits record on remote db
            DB::connection($local_db_connection)->select(DB::raw('UPDATE `audits` SET `synced` = 1 WHERE `audits`.`id` =' . $operate->id));
        } //end of main foreach
    } //end of Sync function
}
