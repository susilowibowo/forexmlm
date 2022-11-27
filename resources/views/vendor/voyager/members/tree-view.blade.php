@extends('voyager::master')

@section('css')
<style>
    .center {
    text-align: center;
    }
</style>

<link rel="stylesheet" href="https://unpkg.com/treeflex/dist/css/treeflex.css">
@stop

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-tree"></i> Member Tree
        </h1>
    </div>
@stop

@section('content')
<div class="tf-tree center">
  <ul>
    <li>
      <span class="tf-nc">Admin</span>
      <ul>
        @foreach ($members as $key => $member)
            @if ($member->parent_id == null)
                <li>
                    <span class="tf-nc">
                        {{$member->name}}
                        <br>
                         $ {{ \App\Models\Member::calculateBonus($member->id) }}
                    </span>
                    @if (count($member->children) >= 1)
                        <ul>
                            @include('vendor.voyager.members.partials.recursive', ['member' => $member])
                        </ul>
                    @endif
                </li>
            @endif
        @endforeach
      </ul>
    </li>
  </ul>
</div>
@stop