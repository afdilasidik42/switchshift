import { Link, useForm } from '@inertiajs/react';
import { Compare, day, Empty, Layout, Pagination, shifts, states, time, useShared, type Change, type Outlet, type PageData } from '../components/ui/Shared';

type Swap = { id: number; requester: string; requester_id: number; partner: string; partner_id: number; position: string; status: string; reason: string; deadline: string };
type Cancellation = { id: number; requester_id: number; status: string; reason: string };
type Timeline = { id: number; event: string; reason: string | null; name: string | null; created_at: string };
type Option = { id: number; user_id: number; work_date: string; name: string; shift: string };
type Preview = { own_day: number; peer_day: number; changes: Change[]; deadline: string };
const open = (state: string) => ['pending_peer', 'pending_supervisor'].includes(state);
const events: Record<string, string> = {
    swap_submitted: 'Pengajuan dibuat', request_cancellation: 'Pembatalan diminta', expired: 'Tenggat tercapai', invalidated: 'Dibatalkan sistem',
};

export default function Swaps({ items, filter, outlet, detail, options, preview, changes, cancellations, timeline }: {
    items: PageData<Swap>; filter: Record<string, string>; outlet: Outlet; detail: Swap | null; options: Option[]; preview: Preview | null;
    changes: Change[]; cancellations: Cancellation[]; timeline: Timeline[];
}) {
    const { account } = useShared();
    const search = useForm({ status: filter.status ?? '', search: filter.search ?? '', from: filter.from ?? '', to: filter.to ?? '' });
    return <Layout title={detail ? 'Pengajuan #' + detail.id : account?.role === 'supervisor' ? 'Persetujuan pertukaran' : 'Pertukaran shift'} description={outlet.name}>
        {detail ? <>
            <Link href="/pertukaran">Kembali ke daftar pengajuan</Link>
            <Detail key={detail.id} item={detail} outlet={outlet} changes={changes} cancellations={cancellations} timeline={timeline} />
        </> : <>
            {account?.role === 'employee' && <Proposal options={options} preview={preview} outlet={outlet} accountId={account.id} />}
            <section className="card"><h2>Daftar pengajuan</h2>
                <form className="filters" onSubmit={(e) => { e.preventDefault(); search.get('/pertukaran', { preserveState: true }); }}>
                    <label>Status<select value={search.data.status} onChange={(e) => search.setData('status', e.target.value)}><option value="">Semua</option>{Object.entries(states).map(([id, label]) => <option value={id} key={id}>{label}</option>)}</select></label>
                    <label>ID pengajuan<input type="number" min="1" value={search.data.search} onChange={(e) => search.setData('search', e.target.value)} /></label>
                    <label>Dari tanggal<input type="date" value={search.data.from} onChange={(e) => search.setData('from', e.target.value)} /></label>
                    <label>Sampai tanggal<input type="date" value={search.data.to} onChange={(e) => search.setData('to', e.target.value)} /></label>
                    <button disabled={search.processing}>Filter</button>
                </form>
                {items.data.length ? <ul className="entries">{items.data.map((item) => <li key={item.id}><Link href={'/pertukaran/' + item.id}>#{item.id} · {item.requester} dan {item.partner}</Link>
                    <small>{states[item.status]} · {item.position}</small></li>)}</ul> : <Empty>Tidak ada pengajuan yang cocok.</Empty>}
                <Pagination page={items} />
            </section>
        </>}
    </Layout>;
}

function Proposal({ options, preview, outlet, accountId }: { options: Option[]; preview: Preview | null; outlet: Outlet; accountId: number }) {
    const form = useForm({ own_day: '', peer_day: '', reason: '', preview: true });
    const own = options.filter((option) => option.user_id === accountId);
    const peers = options.filter((option) => option.user_id !== accountId);
    const checked = preview && preview.own_day === Number(form.data.own_day) && preview.peer_day === Number(form.data.peer_day);
    return <section className="card"><h2>Ajukan pertukaran</h2>
        {!own.length || !peers.length ? <Empty>Belum ada pasangan penugasan tersimpan yang dapat dipilih.</Empty> : <form onSubmit={(e) => {
            e.preventDefault();
            form.transform((data) => ({ ...data, preview: !checked }));
            form.post('/pertukaran', { preserveScroll: true, onSuccess: () => { if (checked) form.reset(); } });
        }}>
            <div className="grid">
                <label>Jadwal kamu<select required value={form.data.own_day} onChange={(e) => form.setData('own_day', e.target.value)}>
                    <option value="">Pilih penugasan</option>{own.map((item) => <option value={item.id} key={item.id}>{day(item.work_date)} · {shifts[item.shift]}</option>)}
                </select></label>
                <label>Jadwal rekan satu posisi<select required value={form.data.peer_day} onChange={(e) => form.setData('peer_day', e.target.value)}>
                    <option value="">Pilih rekan dan tanggal</option>{peers.map((item) => <option value={item.id} key={item.id}>{item.name} · {day(item.work_date)} · {shifts[item.shift]}</option>)}
                </select></label>
            </div>
            {checked && <><h3>Sebelum dan sesudah</h3><Compare rows={preview.changes} /><p>Tenggat {time(preview.deadline, outlet.timezone)} · {outlet.timezone}</p></>}
            <label>Alasan pertukaran<textarea required={Boolean(checked)} maxLength={1000} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} /></label>
            <button className="primary" disabled={form.processing}>{form.processing ? 'Memeriksa…' : checked ? 'Kirim pengajuan' : 'Periksa jadwal dan tampilkan preview'}</button>
        </form>}
    </section>;
}

function Detail({ item, outlet, changes, cancellations, timeline }: { item: Swap; outlet: Outlet; changes: Change[]; cancellations: Cancellation[]; timeline: Timeline[] }) {
    const { account } = useShared();
    const form = useForm({ action: '', reason: '' });
    const id = account?.id;
    const supervisor = account?.role === 'supervisor';
    const cancellation = cancellations.find((entry) => open(entry.status));
    const actions: Record<string, string> = {};
    if (open(item.status)) {
        if (id === item.requester_id) actions.withdraw = 'Batalkan pengajuan';
        if ((item.status === 'pending_peer' && id === item.partner_id) || (item.status === 'pending_supervisor' && supervisor)) {
            actions.accept = supervisor ? 'Setujui pertukaran' : 'Terima sebagai rekan';
            actions.reject = 'Tolak pertukaran';
        }
    }
    if (item.status === 'approved' && !cancellation && !supervisor) actions.request_cancellation = 'Minta pembatalan';
    if (cancellation) {
        if (id === cancellation.requester_id) actions.cancellation_withdraw = 'Tarik permintaan pembatalan';
        if ((cancellation.status === 'pending_peer' && !supervisor && id !== cancellation.requester_id) || (cancellation.status === 'pending_supervisor' && supervisor)) {
            actions.cancellation_accept = supervisor ? 'Setujui pemulihan jadwal' : 'Terima permintaan pembatalan';
            actions.cancellation_reject = 'Tolak pembatalan';
        }
    }
    const reversal = form.data.action.includes('cancellation');
    return <section className="card">
        <h2>{item.requester} dan {item.partner}</h2><p>{item.position} · <span className="tag">{states[item.status]}</span></p>
        <p>Tenggat {time(item.deadline, outlet.timezone)} · {outlet.timezone}</p><p>Alasan pengajuan · {item.reason}</p>
        <Compare rows={changes} />
        {cancellations.length > 0 && <><h3>Riwayat pembatalan</h3><ul className="entries">{cancellations.map((entry) => <li key={entry.id}>{states[entry.status]} · {entry.reason}</li>)}</ul></>}
        {Object.keys(actions).length > 0 && <form onSubmit={(e) => {
            e.preventDefault();
            if (!actions[form.data.action] || !window.confirm(actions[form.data.action] + '?')) return;
            form.post('/pertukaran/' + item.id, { preserveScroll: true, onSuccess: () => form.reset() });
        }}>
            <label>Tindakan<select required value={form.data.action} onChange={(e) => form.setData('action', e.target.value)}><option value="">Pilih tindakan</option>
                {Object.entries(actions).map(([action, label]) => <option key={action} value={action}>{label}</option>)}</select></label>
            {reversal && <><h3>Jadwal setelah pemulihan</h3><Compare rows={changes} reverse /></>}
            {form.data.action && !['accept', 'cancellation_accept'].includes(form.data.action) &&
                <label>Alasan<textarea required maxLength={1000} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} /></label>}
            <button className="primary" disabled={form.processing || !form.data.action}>{form.processing ? 'Menyimpan…' : 'Konfirmasi tindakan'}</button>
        </form>}
        <details><summary>Linimasa pengajuan</summary><ol>{timeline.map((entry) => {
            const status = entry.event.replace(/^(swap|cancellation)_/, '');
            return <li key={entry.id}>{events[entry.event] ?? states[status] ?? entry.event} · {entry.name ?? 'Sistem'} · {time(entry.created_at, outlet.timezone)}{entry.reason && <p>{entry.reason}</p>}</li>;
        })}</ol></details>
    </section>;
}
