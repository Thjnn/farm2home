<?php

namespace App\Services;

use App\Enums\HarvestPlanStatus;
use App\Enums\SupplySource;
use App\Models\Farmer;
use App\Models\HarvestPlan;
use App\Models\SupplyReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyReportService
{
    /**
     * Ngày đã có kế hoạch thu hoạch được chốt thì không sửa sản lượng nữa.
     */
    public function isLocked(string $date): bool
    {
        return HarvestPlan::whereDate('delivery_date', $date)
            ->where('status', '!=', HarvestPlanStatus::Draft)
            ->exists();
    }

    /**
     * Save a farmer's reported supply. Blank or zero removes the existing report.
     *
     * @param  array<string, array<int|string, string|float|null>>  $supply  ['Y-m-d' => [product_id => kg]]
     *
     * @throws ValidationException when a product is not grown by the farmer or the date is locked
     */
    public function save(Farmer $farmer, array $supply, SupplySource $source): int
    {
        $ownProductIds = $farmer->products()->pluck('products.id')->all();

        return DB::transaction(function () use ($farmer, $supply, $source, $ownProductIds) {
            $saved = 0;

            foreach ($supply as $date => $byProduct) {
                if ($this->isLocked($date)) {
                    $label = CarbonImmutable::parse($date)->format('d/m');
                    throw ValidationException::withMessages(['supply' => "Kế hoạch ngày {$label} đã chốt, không sửa được sản lượng."]);
                }

                foreach ($byProduct as $productId => $kg) {
                    if (! in_array((int) $productId, $ownProductIds, true)) {
                        throw ValidationException::withMessages(['supply' => 'Loại rau không thuộc danh sách của nông hộ.']);
                    }

                    $key = ['farmer_id' => $farmer->id, 'product_id' => (int) $productId, 'available_date' => $date];

                    if ($kg === null || $kg === '' || (float) $kg <= 0) {
                        SupplyReport::where($key)->delete();

                        continue;
                    }

                    SupplyReport::updateOrCreate($key, ['quantity_kg' => round((float) $kg, 2), 'source' => $source]);
                    $saved++;
                }
            }

            return $saved;
        });
    }
}
