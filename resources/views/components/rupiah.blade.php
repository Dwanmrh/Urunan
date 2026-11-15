@props(['jumlah' => 0])
<span {{ $attributes }}>Rp {{ number_format(abs((int) $jumlah), 0, ',', '.') }}</span>
