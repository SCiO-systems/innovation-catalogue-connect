<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Innovation extends Model
{
    //Database properties
    protected $connection = 'mongodb';
    protected $collection = 'innovations';

    //Primary Key properties
    protected $primaryKey = 'innovId';
    protected $keyType = 'string';
}
