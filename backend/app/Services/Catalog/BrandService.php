<?php

namespace App\Services\Catalog;

use App\Exceptions\BusinessRuleException;
use App\Models\Brand;
use App\Services\Media\ImageStorage;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class BrandService
{
    public function __construct(private readonly ImageStorage $images) {}

    public function create(array $data, ?UploadedFile $logo): Brand
    {
        $brand = new Brand(Arr::except($data, ['logo', 'remove_logo']));

        if ($logo) {
            $brand->logo_path = $this->images->store($logo, 'brands');
        }

        $brand->save();

        AuditLog::info('brand.created', ['brand_id' => $brand->id]);

        return $brand;
    }

    public function update(Brand $brand, array $data, ?UploadedFile $logo): Brand
    {
        $oldLogo = $brand->logo_path;
        $brand->fill(Arr::except($data, ['logo', 'remove_logo']));

        if ($logo) {
            $brand->logo_path = $this->images->store($logo, 'brands');
        } elseif (! empty($data['remove_logo'])) {
            $brand->logo_path = null;
        }

        $brand->save();

        if ($oldLogo !== $brand->logo_path) {
            $this->images->delete($oldLogo);
        }

        AuditLog::info('brand.updated', ['brand_id' => $brand->id]);

        return $brand;
    }

    /** Refused while products still reference the brand, so they can be reassigned first. */
    public function delete(Brand $brand): void
    {
        $productCount = $brand->products()->count();

        if ($productCount > 0) {
            throw BusinessRuleException::conflict("This brand still has {$productCount} product(s). Reassign them or deactivate the brand instead.");
        }

        $brand->delete();

        AuditLog::info('brand.deleted', ['brand_id' => $brand->id]);
    }
}
