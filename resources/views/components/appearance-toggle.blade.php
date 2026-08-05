{{--
    Light/dark switch.

    `$flux.dark` is Flux's reactive boolean: reading it resolves a "system"
    preference against the OS setting, and writing it persists the choice to
    localStorage. The three-way choice (light/dark/system) lives on the
    appearance settings page.
--}}
<flux:tooltip :content="__('Toggle light or dark mode')" position="bottom">
    <flux:button
        x-data
        x-on:click="$flux.dark = ! $flux.dark"
        variant="subtle"
        size="sm"
        square
        :aria-label="__('Toggle light or dark mode')"
        {{ $attributes }}
        data-test="appearance-toggle"
    >
        <flux:icon.sun x-cloak x-show="$flux.dark" class="size-5" />
        <flux:icon.moon x-cloak x-show="! $flux.dark" class="size-5" />
    </flux:button>
</flux:tooltip>
