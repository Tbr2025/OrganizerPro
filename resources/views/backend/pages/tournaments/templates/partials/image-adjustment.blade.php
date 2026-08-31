{{--
    Reusable image adjustment component (brightness/contrast + presets).
    Used on award_poster, playing_xi, and match_summary generate panels.

    @param string $placeholder  The image placeholder name (e.g. 'player_image', 'featured_player_image')
--}}
<div x-data="imageAdjustment('{{ $placeholder }}')" x-init="syncHidden()"
     class="p-3 rounded-lg bg-white/60 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between mb-2">
        <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Enhancement</span>
        <select x-model="selectedPreset" @change="applyPreset(); syncHidden()"
                class="text-[11px] rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 py-0.5 px-1.5 min-w-0">
            <option value="">-- Preset --</option>
            <template x-for="p in allPresets" :key="p.name">
                <option :value="p.name" x-text="p.name"></option>
            </template>
        </select>
    </div>
    <div class="space-y-1.5">
        <div>
            <div class="flex items-center justify-between">
                <label class="text-[10px] text-gray-500">Brightness</label>
                <span class="text-[10px] font-mono text-gray-500 tabular-nums" x-text="brightness > 0 ? '+' + brightness : brightness"></span>
            </div>
            <input type="range" min="-50" max="50" x-model.number="brightness"
                   @input="selectedPreset = ''; syncHidden()"
                   class="w-full h-1 bg-gray-200 dark:bg-gray-600 rounded-lg appearance-none cursor-pointer accent-amber-500">
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label class="text-[10px] text-gray-500">Contrast</label>
                <span class="text-[10px] font-mono text-gray-500 tabular-nums" x-text="displayContrast > 0 ? '+' + displayContrast : displayContrast"></span>
            </div>
            <input type="range" min="-50" max="50" x-model.number="displayContrast"
                   @input="selectedPreset = ''; syncHidden()"
                   class="w-full h-1 bg-gray-200 dark:bg-gray-600 rounded-lg appearance-none cursor-pointer accent-amber-500">
        </div>
    </div>
    <div class="flex items-center gap-2 mt-2">
        <button type="button" @click="saveCustomPreset()" class="text-[10px] text-purple-600 dark:text-purple-400 hover:text-purple-800 font-medium">
            Save as preset
        </button>
        <template x-if="selectedPreset && isCustomPreset(selectedPreset)">
            <button type="button" @click="deleteCustomPreset(selectedPreset)" class="text-[10px] text-red-500 hover:text-red-700 font-medium">
                Delete
            </button>
        </template>
    </div>
</div>
