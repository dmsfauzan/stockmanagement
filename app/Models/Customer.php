<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'type', 'contact_person', 'phone', 'email', 'address', 'status'])]
class Customer extends Model
{
    use HasFactory, SoftDeletes;
}
