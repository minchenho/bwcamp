<?php

namespace App\Enums;

enum AttendanceStatus: int
{
    case No = 0;
    case Yes = 1;
    case Maybe = 2;
    case Unreachable = 3;
    case Partial = 4;
    case NotYetCalled = 5;

    // 取得中文標籤
    public function label(): string
    {
        return match($this) {
            self::No => '不參加',
            self::Yes => '參加',
            self::Maybe => '尚未決定',
            self::Unreachable => '聯絡不上',
            self::Partial => '無法全程',
            self::NotYetCalled => '尚未聯絡',
        };
    }

    // 新增：從標籤（No/Yes/Maybe/Unreachable/Partial/NotYetCalled）找到對應的 Enum 實例
    public static function fromLabel(string $label): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }
        return self::NotYetCalled; // 回傳一個預設值
    }

    // 新增：從中文標籤直接取得對應的 CSS class
    public static function colorFromLabel(string $label): string
    {
        return self::fromLabel($label)->colorClass();
    }

    // 取得對應的 CSS class
    public function colorClass(): string
    {
        return match($this) {
            self::Yes => 'text-success',
            self::No => 'text-danger',
            self::Maybe => 'text-warning', 
            self::Unreachable => 'text-secondary', 
            self::Partial => 'text-info', 
            self::NotYetCalled => 'text-light',
        };
    }
}
