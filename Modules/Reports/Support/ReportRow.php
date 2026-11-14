<?php

namespace Modules\Reports\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * A row of a report table: the grouped SQL is wrapped as `ReportRow::query()->fromSub($sql, 'report')`
 * so AdminTable can page, sort and filter it. Never saved.
 */
class ReportRow extends Model
{
    protected $table = 'report';

    public $timestamps = false;

    protected $guarded = [];
}
