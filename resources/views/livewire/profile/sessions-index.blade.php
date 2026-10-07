<div>
    <x-ui.page-header title="Sesi Aktif" subtitle="Perangkat/browser yang sedang login ke akun Anda">
        <x-slot:actions>
            <a href="{{ route('profile.edit') }}" class="app-btn app-btn-secondary">Kembali ke Profil</a>
            <x-ui.confirm action="logoutOthers" title="Logout Sesi Lain" message="Keluarkan semua sesi selain perangkat ini?" confirm-label="Logout Sesi Lain" variant="danger" class="app-btn app-btn-danger">Logout Sesi Lain</x-ui.confirm>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Perangkat</th>
                        <th>IP</th>
                        <th>Terakhir Aktif</th>
                        <th class="text-right">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td class="max-w-md truncate text-app-muted">{{ $session['agent'] ?? '—' }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $session['ip'] ?? '—' }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $session['last']->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap text-right">
                                @if ($session['current'])
                                    <x-ui.status-badge status="active" label="Perangkat ini" />
                                @else
                                    <span class="text-xs text-app-muted">Aktif</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-ui.empty-state title="Tidak ada sesi" message="Belum ada sesi tercatat." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <p class="mt-4 text-xs text-app-muted">Catatan: daftar ini memakai driver session database. Login &amp; logout juga tercatat di Audit Logs.</p>
</div>
