<?php

namespace App\Services\Marketing;

use App\Models\LineRichMenu;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RichMenuService
{
    public const LAYOUTS = [
        'compact_4' => ['rows' => 1, 'cols' => 4, 'buttons' => 4],
        'compact_6' => ['rows' => 2, 'cols' => 3, 'buttons' => 6],
        'full_4' => ['rows' => 2, 'cols' => 2, 'buttons' => 4],
        'full_6' => ['rows' => 2, 'cols' => 3, 'buttons' => 6],
        'full_12' => ['rows' => 3, 'cols' => 4, 'buttons' => 12],
    ];

    /**
     * Validate buttons array shape against the layout.
     *
     * @param array<int, array{label:string, action:string, value:string}> $buttons
     */
    public function validateButtons(string $layout, array $buttons): void
    {
        if (! isset(self::LAYOUTS[$layout])) {
            throw ValidationException::withMessages(['layout' => 'Invalid layout']);
        }
        $expected = self::LAYOUTS[$layout]['buttons'];
        if (count($buttons) !== $expected) {
            throw ValidationException::withMessages(['buttons' => "Layout {$layout} requires exactly {$expected} buttons"]);
        }
        foreach ($buttons as $i => $b) {
            if (empty($b['label']) || empty($b['action'])) {
                throw ValidationException::withMessages(["buttons.{$i}" => 'label and action are required']);
            }
            if (! in_array($b['action'], ['url', 'message', 'postback'])) {
                throw ValidationException::withMessages(["buttons.{$i}.action" => 'action must be url|message|postback']);
            }
        }
    }

    /**
     * Set this menu as the only active one for the branch.
     */
    public function activate(LineRichMenu $menu): LineRichMenu
    {
        LineRichMenu::where('branch_id', $menu->branch_id)
            ->where('id', '!=', $menu->id)
            ->update(['is_active' => false]);
        $menu->is_active = true;
        $menu->save();

        return $menu;
    }

    /**
     * Stub for syncing to LINE — in production it would call LINE Messaging API.
     */
    public function syncToLine(LineRichMenu $menu): array
    {
        // No-op stub. Real implementation requires LINE API and uploaded image (1200x405 / 2500x1686).
        $menu->line_rich_menu_id = $menu->line_rich_menu_id ?: ('richmenu-'.Str::random(16));
        $menu->save();

        return ['line_rich_menu_id' => $menu->line_rich_menu_id, 'simulated' => true];
    }
}
