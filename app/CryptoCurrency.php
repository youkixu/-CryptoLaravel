<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CryptoCurrency extends Model
{
  public function Portfolio() {
    return $this->belongsTo('App\Portfolio');
  }
}
