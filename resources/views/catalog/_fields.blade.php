<x-input name="name" label="Name" :value="$item?->name" required maxlength="150" :id="$prefix.'-name'" />
@if ($type === 'categories')
    <x-input name="description" label="Description" :value="$item?->description" maxlength="255" :id="$prefix.'-desc'" />
@elseif ($type === 'manufacturers')
    <div class="grid gap-4 sm:grid-cols-2">
        <x-input name="country" label="Country" :value="$item?->country" maxlength="80" :id="$prefix.'-country'" />
        <x-input name="phone" label="Phone" :value="$item?->phone" maxlength="30" :id="$prefix.'-phone'" />
    </div>
    <x-input name="email" type="email" label="Email" :value="$item?->email" :id="$prefix.'-email'" />
@endif
<x-toggle name="is_active" label="Active" :checked="$item?->is_active ?? true" />
