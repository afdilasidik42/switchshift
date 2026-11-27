import { Link, router, useForm } from '@inertiajs/react';
import { Compare, day, Empty, Layout, Pagination, shifts, states, time, useShared, type Change, type Outlet, type PageData } from '../components/ui/Shared';

export function Login() {
    const form = useForm({ employee_code: '', password: '' });
    return <Layout title="Masuk ke Roti’O" description="Kelola jadwal kerja dan pertukaran shift outlet.">
        <form className="card narrow" onSubmit={(event) => { event.preventDefault(); form.post('/masuk', { onFinish: () => form.reset('password') }); }}>
            <label>ID karyawan<input name="employee_code" autoComplete="username" required value={form.data.employee_code} onChange={(e) => form.setData('employee_code', e.target.value)} /></label>
            <label>Password<input name="password" type="password" autoComplete="current-password" required value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></label>
            <button className="primary" disabled={form.processing}>{form.processing ? 'Sedang masuk…' : 'Masuk'}</button>
            <p className="muted">Hubungi admin jika perlu reset password.</p>
        </form>
    </Layout>;
}

export function Password() {
    const { account } = useShared();
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });
    return <Layout title={account?.must_change_password ? 'Ganti password sementara' : 'Profil & password'}
        description={account?.must_change_password ? 'Buat password pribadi untuk mulai memakai aplikasi.' : account?.name + ' · ' + account?.employee_code}>
        <form className="card narrow" onSubmit={(e) => { e.preventDefault(); form.post('/password', { onSuccess: () => form.reset() }); }}>
            <label>Password saat ini<input type="password" autoComplete="current-password" required value={form.data.current_password} onChange={(e) => form.setData('current_password', e.target.value)} /></label>
            <label>Password baru<input type="password" autoComplete="new-password" minLength={12} maxLength={72} required value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></label>
            <label>Ulangi password baru<input type="password" autoComplete="new-password" required value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} /></label>
            <button className="primary" disabled={form.processing}>Simpan password</button>
        </form>
    </Layout>;
}

type Agenda = { id: number; work_date: string; shift: string };
type Queue = { id: number; status: string; deadline: string; cancellation_status: string | null };
type Shortage = { id: number; name: string; date: string; shift: string };
export function Dashboard({ outlet, today, agenda, queue, summary, pending, shortages }: {
    outlet: Outlet | null; today: string; agenda: Agenda[]; queue: Queue[]; summary: Record<string, number>; pending: number; shortages: Shortage[];
}) {
    const { account } = useShared();
    return <Layout title="Dashboard" description={day(today) + (outlet ? ' · ' + outlet.name : '')}>
        <h2>Halo, {account?.name}</h2>
        {account?.role === 'admin' ? <div className="grid">{Object.entries(summary).map(([label, count]) =>
            <section key={label} className="card"><h3>{label}</h3><strong>{count}</strong></section>)}</div> : !outlet ?
            <Empty>Akun belum memiliki outlet aktif. Hubungi admin.</Empty> : <>
            <div className="actions"><Link className="button primary" href="/jadwal">Lihat jadwal</Link>
                <Link className="button" href="/pertukaran">{account?.role === 'employee' ? 'Ajukan pertukaran' : 'Buka persetujuan'}</Link>
                <Link href="/riwayat">Konfirmasi tertunda ({pending})</Link></div>
            <div className="grid">
                {account?.role === 'employee' && <section className="card"><h2>Jadwal terdekat</h2>
                    {agenda.length ? <ul className="entries">{agenda.map((item) => <li key={item.id}>{item.work_date === today ? 'Hari ini' : day(item.work_date)} · {shifts[item.shift]}</li>)}</ul> : <Empty>Belum ada jadwal tersimpan.</Empty>}</section>}
                <section className="card"><h2>{account?.role === 'employee' ? 'Pengajuan saya' : 'Menunggu keputusan'}</h2>
                    {queue.length ? <ul className="entries">{queue.map((item) => <li key={item.id}><Link href={'/pertukaran/' + item.id}>Pengajuan #{item.id}</Link> · {states[item.cancellation_status ?? item.status]}<small>Tenggat {time(item.deadline, outlet.timezone)}</small></li>)}</ul> : <Empty>Belum ada pengajuan di antrean.</Empty>}</section>
                {account?.role === 'supervisor' && <section className="card"><h2>Shift perlu pengganti</h2>
                    {shortages.length ? <ul className="entries">{shortages.map((item) => <li key={item.id}>{day(item.date)} · {shifts[item.shift]} · {item.name}</li>)}</ul> : <Empty>Tidak ada penugasan karyawan nonaktif dalam tujuh hari ke depan.</Empty>}</section>}
            </div>
        </>}
    </Layout>;
}

type Notice = { id: number; message: string; target: string; created_at: string; read_at: string | null };
export function Notifications({ items, unreadOnly }: { items: PageData<Notice>; unreadOnly: boolean }) {
    return <Layout title="Notifikasi">
        <div className="actions"><Link href="/notifikasi" aria-current={!unreadOnly ? 'page' : undefined}>Semua</Link><Link href="/notifikasi?unread=1" aria-current={unreadOnly ? 'page' : undefined}>Belum dibaca</Link>
            <Link className="button" as="button" method="post" href="/notifikasi">Tandai semua dibaca</Link></div>
        <section className="card">{items.data.length ? <ul className="entries">{items.data.map((item) => <li key={item.id}>
            <Link href={item.target}>{item.message}</Link><small>{time(item.created_at)} · {item.read_at ? 'Sudah dibaca' : 'Belum dibaca'}</small>
            {!item.read_at && <Link as="button" method="post" href={'/notifikasi/' + item.id}>Tandai dibaca</Link>}
        </li>)}</ul> : <Empty>Tidak ada notifikasi dalam tampilan ini.</Empty>}
        <Pagination page={items} /></section>
    </Layout>;
}

type HistoryItem = { id: number; kind: string; reason: string | null; actor: string; created_at: string; details: Change[]; acknowledgements: { user_id: number; name: string; confirmed_at: string | null }[] };
export function History({ items, filter, outlet }: { items: PageData<HistoryItem>; filter: Record<string, string>; outlet: Outlet }) {
    const { account } = useShared();
    const form = useForm({ from: filter.from ?? '', to: filter.to ?? '', id: filter.id ?? '', kind: filter.kind ?? '' });
    return <Layout title="Riwayat & konfirmasi" description={outlet.name}>
        <form className="filters" onSubmit={(e) => { e.preventDefault(); form.get('/riwayat', { preserveState: true }); }}>
            <label>Dari tanggal<input type="date" value={form.data.from} onChange={(e) => form.setData('from', e.target.value)} /></label>
            <label>Sampai tanggal<input type="date" value={form.data.to} onChange={(e) => form.setData('to', e.target.value)} /></label>
            <label>ID perubahan<input type="number" min="1" value={form.data.id} onChange={(e) => form.setData('id', e.target.value)} /></label>
            <label>Jenis<select value={form.data.kind} onChange={(e) => form.setData('kind', e.target.value)}><option value="">Semua</option>{['penerbitan', 'revisi', 'pertukaran', 'pemulihan'].map((kind) => <option key={kind}>{kind}</option>)}</select></label>
            <button disabled={form.processing}>Filter</button>
        </form>
        {items.data.length ? items.data.map((item) => <section className="card" key={item.id}>
            <h2>#{item.id} · {item.kind}</h2><p>{item.actor} · {time(item.created_at, outlet.timezone)}</p>{item.reason && <p>{item.reason}</p>}
            <details><summary>Lihat perubahan jadwal</summary><Compare rows={item.details} /></details>
            {item.acknowledgements.map((ack) => <p key={ack.user_id}>{ack.name} · {ack.confirmed_at ? 'Dikonfirmasi ' + time(ack.confirmed_at, outlet.timezone) : 'Belum dikonfirmasi'}</p>)}
            {account?.role === 'employee' && item.acknowledgements.some((ack) => !ack.confirmed_at) &&
                <button className="primary" onClick={(e) => { const button = e.currentTarget; button.disabled = true; router.post('/konfirmasi/' + item.id, {}, { preserveScroll: true, onFinish: () => { button.disabled = false; } }); }}>Saya sudah melihat jadwal versi ini</button>}
        </section>) : <Empty>Belum ada riwayat yang cocok.</Empty>}
        <Pagination page={items} />
    </Layout>;
}
