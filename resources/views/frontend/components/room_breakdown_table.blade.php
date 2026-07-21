@foreach ($groups as $group)
  <div class="ld-room-type-title">
    {{ $group['title'] }} <span class="text-muted fw-normal">({{ $group['total'] }})</span>
  </div>
  <table class="ld-room-table">
    <tbody>
    @foreach ($group['variants'] as $variant)
      <tr>
        <td class="ld-room-td-count">{{ $group['variants']->count() > 1 ? $variant['count'].'x' : '1x' }}</td>
        <td class="ld-room-td-dims">
          <div class="ld-dims-row">
            @foreach ($variant['dimensions'] as $dim)
              <span class="ld-dim-item me-2">
                <span class="ld-dim-label badge bg-label-secondary">{{ $dim['name'] }}</span>
                <span class="ld-dim-value">{{ $dim['value'] }} {{ $dim['unit'] }}</span>
              </span>
            @endforeach
          </div>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
@endforeach
