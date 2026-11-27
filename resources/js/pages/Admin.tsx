import { useForm } from '@inertiajs/react';
import { Empty, Layout, roles, states, type Outlet } from '../components/ui/Shared';

type Position = Omit<Outlet, 'timezone'>;
type User = { id: number; employee_code: string; name: string; role: keyof typeof roles; outlet_id: number | null; position_id: number | null; joined_on: string | null; disabled_at: string | null };
type Assignment = { outlet_id: number; user_id: number };
type Props = { outlets: Outlet[]; positions: Position[]; users: User[]; assignments: Assignment[]; zones: string[]; swapCounts: Record<string, number> };

export default function Admin({ outlets, positions, users, assignments, zones, swapCounts }: Props) {
    const form = useForm({ outlet_id: '', user_id: '' });
    return <Layout title="Administrasi" description="Kelola outlet, posisi, akun, dan penugasan SPV.">
        <div className="actions"><a href="#outlet">Outlet</a><a href="#posisi">Posisi</a><a href="#akun">Akun</a><a href="#spv">Penugasan SPV</a></div>
        <div className="grid">
            <section className="card" id="outlet"><h2>Outlet</h2><details><summary>Tambah outlet</summary><Master type="outlets" zones={zones} /></details>
                {outlets.map((item) => <details key={item.id}><summary>{item.name} · {item.active ? 'Aktif' : 'Nonaktif'}</summary><Master type="outlets" item={item} zones={zones} /></details>)}</section>
            <section className="card" id="posisi"><h2>Posisi</h2><details><summary>Tambah posisi</summary><Master type="positions" /></details>
                {positions.map((item) => <details key={item.id}><summary>{item.name} · {item.active ? 'Aktif' : 'Nonaktif'}</summary><Master type="positions" item={item} /></details>)}</section>
        </div>
        <section className="card" id="akun"><h2>Akun</h2><details><summary>Tambah akun</summary><AccountForm outlets={outlets} positions={positions} /></details>
            {users.map((user) => <details key={user.id}><summary>{user.name} · {user.employee_code} · {roles[user.role]} · {user.disabled_at ? 'Nonaktif' : 'Aktif'}</summary>
                <div className="grid"><AccountForm user={user} outlets={outlets} positions={positions} />{!user.disabled_at && <AccountActions user={user} />}</div>
            </details>)}
        </section>
        <section className="card" id="spv"><h2>Penugasan SPV</h2>
            {!outlets.length && <Empty>Tambahkan outlet untuk membuat penugasan.</Empty>}
            <ul className="entries">{outlets.map((outlet) => {
                const spv = users.find((user) => user.id === assignments.find((assignment) => assignment.outlet_id === outlet.id)?.user_id);
                return <li key={outlet.id}>{outlet.name} · {spv && !spv.disabled_at ? spv.name : 'Belum punya SPV aktif'}</li>;
            })}</ul>
            <form className="filters" onSubmit={(e) => { e.preventDefault(); form.post('/admin/penugasan', { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                <label>Outlet<select required value={form.data.outlet_id} onChange={(e) => form.setData('outlet_id', e.target.value)}><option value="">Pilih outlet</option>
                    {outlets.filter((item) => item.active).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                <label>SPV<select required value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)}><option value="">Pilih SPV</option>
                    {users.filter((user) => user.role === 'supervisor' && !user.disabled_at).map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}</select></label>
                <button className="primary" disabled={form.processing}>Simpan penugasan</button>
            </form>
        </section>
        <section className="card"><h2>Ringkasan pertukaran</h2>{Object.keys(swapCounts).length ? <ul>{Object.entries(swapCounts).map(([status, count]) => <li key={status}>{states[status]} · {count}</li>)}</ul> : <Empty>Belum ada pengajuan.</Empty>}</section>
    </Layout>;
}

function Master({ type, item, zones = [] }: { type: 'outlets' | 'positions'; item?: Position | Outlet; zones?: string[] }) {
    const form = useForm({ code: item?.code ?? '', name: item?.name ?? '', active: Boolean(item?.active ?? true), timezone: item && 'timezone' in item ? item.timezone : zones[0] ?? '' });
    return <form onSubmit={(e) => {
        e.preventDefault();
        if (!form.data.active && item?.active && !window.confirm('Nonaktifkan ' + item.name + '?')) return;
        form.post('/admin/master/' + type + (item ? '/' + item.id : ''), { preserveScroll: true, onSuccess: () => { if (!item) form.reset(); } });
    }}>
        <label>Kode<input required maxLength={32} value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} /></label>
        <label>Nama<input required maxLength={120} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
        {type === 'outlets' && <label>Zona waktu<select value={form.data.timezone} onChange={(e) => form.setData('timezone', e.target.value)}>{zones.map((value) => <option key={value}>{value}</option>)}</select></label>}
        <label className="actions"><input type="checkbox" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} />Aktif</label>
        <button disabled={form.processing}>Simpan {type === 'outlets' ? 'outlet' : 'posisi'}</button>
    </form>;
}

function AccountForm({ user, outlets, positions }: { user?: User; outlets: Outlet[]; positions: Position[] }) {
    const form = useForm({ name: user?.name ?? '', employee_code: user?.employee_code ?? '', role: user?.role ?? 'employee',
        outlet_id: String(user?.outlet_id ?? ''), position_id: String(user?.position_id ?? ''), joined_on: user?.joined_on ?? '', password: '' });
    return <form onSubmit={(e) => {
        e.preventDefault();
        form.transform((data) => {
            const base = { name: data.name, employee_code: data.employee_code, ...(data.role === 'employee' ? { position_id: data.position_id } : {}) };
            return user ? base : { ...base, role: data.role, password: data.password, ...(data.role === 'employee' ? { outlet_id: data.outlet_id, joined_on: data.joined_on } : {}) };
        });
        form.post('/admin/akun' + (user ? '/' + user.id : ''), { preserveScroll: true, onSuccess: () => { if (!user) form.reset(); } });
    }}>
        <label>Nama<input required maxLength={120} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /></label>
        <label>ID karyawan<input required maxLength={32} value={form.data.employee_code} onChange={(e) => form.setData('employee_code', e.target.value)} /></label>
        {!user && <label>Peran<select value={form.data.role} onChange={(e) => form.setData('role', e.target.value as User['role'])}>
            {Object.entries(roles).map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></label>}
        {form.data.role === 'employee' && <>
            {user ? <p>{outlets.find((item) => item.id === user.outlet_id)?.name} · Mulai {user.joined_on}</p> : <>
                <label>Outlet<select required value={form.data.outlet_id} onChange={(e) => form.setData('outlet_id', e.target.value)}><option value="">Pilih outlet</option>
                    {outlets.filter((item) => item.active).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                <label>Tanggal bergabung<input type="date" required value={form.data.joined_on} onChange={(e) => form.setData('joined_on', e.target.value)} /></label>
            </>}
            <label>Posisi<select required value={form.data.position_id} onChange={(e) => form.setData('position_id', e.target.value)}><option value="">Pilih posisi</option>
                {positions.filter((item) => item.active).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
        </>}
        {!user && <label>Password sementara<input type="password" autoComplete="new-password" required minLength={12} maxLength={72} value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /></label>}
        <button disabled={form.processing}>Simpan akun</button>
    </form>;
}

function AccountActions({ user }: { user: User }) {
    const reset = useForm({ password: '' });
    const disable = useForm({});
    return <section>
        <form onSubmit={(e) => { e.preventDefault(); if (window.confirm('Reset password dan cabut sesi lama?')) reset.post('/admin/akun/' + user.id + '/reset', { preserveScroll: true, onSuccess: () => reset.reset() }); }}>
            <label>Password sementara baru<input type="password" autoComplete="new-password" minLength={12} maxLength={72} required value={reset.data.password} onChange={(e) => reset.setData('password', e.target.value)} /></label>
            <button disabled={reset.processing}>Reset password</button>
        </form>
        <button disabled={disable.processing} onClick={() => { if (window.confirm('Nonaktifkan ' + user.name + ' dan cabut aksesnya?')) disable.post('/admin/akun/' + user.id + '/disable', { preserveScroll: true }); }}>Nonaktifkan akun</button>
    </section>;
}
