@foreach ($member->grandChildren()->get() as $child)
    <li>
        <span class="tf-nc">
            {{$child->name}}
            <br>
            $ {{ \App\Models\Member::calculateBonus($child->id) }}
        </span>
        @if (count($child->children) >= 1)
            <ul>
                @include('vendor.voyager.members.partials.recursive', ['member' => $child])
            </ul>
        @endif  
    </li>  
@endforeach