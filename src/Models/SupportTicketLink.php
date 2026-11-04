<?php

namespace WebBlocks\Support\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketLink extends Model
{
  protected $table = 'webblocks_support_ticket_links';

  protected $guarded = [];

  protected $hidden = ['tracking_url'];

  protected function casts(): array
  {
    return ['tracking_url' => 'encrypted'];
  }
}
