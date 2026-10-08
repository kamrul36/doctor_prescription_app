<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\CatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Soft delete only: finalized visits keep the names they were written with. */
class DeleteCatalogItemAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CatalogCache $cache,
    ) {}

    public function handle(Model $item): void
    {
        DB::transaction(function () use ($item) {
            $item->delete();
            $this->audit->log('catalog.'.$item->getTable().'.deleted', $item);
        });

        $this->cache->flush();
    }
}
