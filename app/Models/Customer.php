<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'type', 'contact_person', 'phone', 'email', 'address', 'status'])]
class Customer extends Model
{
    use HasFactory;
}
