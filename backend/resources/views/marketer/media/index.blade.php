<x-layouts.marketer :title="__('marketing.media.title')">
    <x-slot:header><x-ui.page-header :title="__('marketing.media.title')" /></x-slot:header>
    @include('marketer.media._library')
</x-layouts.marketer>
