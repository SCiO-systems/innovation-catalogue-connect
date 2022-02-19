<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class User extends Model
{
    //Database properties
    protected $connection = 'mongodb';
    protected $collection = 'users';

    //Primary Key properties
    protected $primaryKey = 'userId';
    protected $keyType = 'string';
}
