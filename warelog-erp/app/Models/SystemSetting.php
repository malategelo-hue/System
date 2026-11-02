<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function defaults(): array
    {
        return [
            'system_name' => 'WareLog ERP System',
            'company_name' => 'WareLog Solutions',
            'warehouse_name' => 'Main Warehouse',
            'address' => 'San Jose del Monte, Bulacan',
            'contact_no' => '09-12-345-6789',
            'currency' => 'PHP',
            'low_stock_threshold' => '10',
            'low_stock_alerts' => '1',
            'auto_codes' => '1',
            'show_decimals' => '1',
            'dark_mode' => '0',
            'date_format' => 'm/d/Y',
            'timezone' => 'Asia/Manila',
            'auto_logout' => '1',
            'session_timeout' => '120',
            'backup_daily' => '1',
            'require_gps_proof' => '1',
        ];
    }

    public static function values(): array
    {
        return array_replace(
            self::defaults(),
            self::query()->pluck('value', 'key')->all(),
        );
    }

    public static function saveValues(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value],
            );
        }
    }

    public static function resetToDefaults(): void
    {
        self::saveValues(self::defaults());
    }
}
