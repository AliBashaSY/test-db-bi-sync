<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Task extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $auditInclude = [
        'name',
        'desc',
        'created_at'
    ];
    /*this function is to add table name of the model to audits table */
    public function transformAudit(array $data): array
    {
        $data['auditable_table'] =  $this->getTable();
        return $data;
    }
}
