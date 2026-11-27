import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState, type FormEvent } from 'react';
import { day, Empty, Layout, shifts, useShared, type Outlet } from '../components/ui/Shared';

type Member = { id: number; name: string; position: string; position_name: string; joined: string; inactive: string | null };
type Day = { id: number; user_id: number; work_date: string; shift: string };
type Props = { week: string; version: number; outlet: Outlet; days: Day[]; members: Member[]; dates: string[]; reservations: Record<number, number>; localTime: string; previous: string; next: string };

export default function Schedule(props: Props) {
    return <Editor key={props.week + ':' + props.version} {...props} />;
}

function Editor({ week, version, outlet, days, members, dates, reservations, previous, next, localTime }: Props) {
    const { account } = useShared();
    const [editing, setEditing] = useState(false);
    const [position, setPosition] = useState('');
    const [memberName, setMemberName] = useState('');
    const [singleDay, setSingleDay] = useState('');
    const [chosenWeek, setChosenWeek] = useState(week);
    const form = useForm({ week, version, cells: {} as Record<number, Record<string, string>>, reason: '', confirmed: [] as number[] });
    useEffect(() => {
        if (!form.isDirty) return;
        const cancel = (event: BeforeUnloadEvent) => event.preventDefault();
        const detach = router.on('before', (event) => {
            if (event.detail.visit.method === 'get' && !event.detail.visit.only?.length && !window.confirm('Tinggalkan perubahan jadwal yang belum disimpan?')) return false;
        });
        window.addEventListener('beforeunload', cancel);
        return () => { detach(); window.removeEventListener('beforeunload', cancel); };
    }, [form.isDirty]);

    const current = new Map(days.map((item) => [item.user_id + ':' + item.work_date, item]));
    const value = (id: number, date: string) => form.data.cells[id]?.[date] ?? current.get(id + ':' + date)?.shift ?? '';
    const changed = days.filter((item) => value(item.user_id, item.work_date) !== item.shift);
    const conflicts = [...new Set(changed.map((item) => reservations[item.id]).filter(Boolean))];
    const visibleDates = singleDay && !editing ? [singleDay] : dates;
    const visibleMembers = members.filter((member) => (!position || member.position === position) && member.name.toLowerCase().includes(memberName.toLowerCase()));
    function submit(event: FormEvent) {
        event.preventDefault();
        if (conflicts.length && !window.confirm('Simpan revisi dan batalkan pengajuan ' + conflicts.map((id) => '#' + id).join(', ') + '?')) return;
        form.transform((data) => ({ ...data, confirmed: conflicts }));
        form.post('/jadwal', { preserveScroll: true, onSuccess: () => { form.reset(); setEditing(false); } });
    }

    return <Layout title="Jadwal shift" description={outlet.name + ' · ' + outlet.timezone}>
        <div className="actions">
            <Link className="button" href={'/jadwal?week=' + previous}>Minggu sebelumnya</Link>
            <Link className="button" href={'/jadwal?week=' + next}>Minggu berikutnya</Link>
            {account?.role === 'supervisor' && !editing && <button className="primary" onClick={() => { setEditing(true); setPosition(''); setMemberName(''); }}>Edit jadwal</button>}
        </div>
        <form className="filters" onSubmit={(e) => { e.preventDefault(); router.get('/jadwal', { week: chosenWeek }); }}>
            <label>Pilih tanggal dalam minggu<input type="date" value={chosenWeek} required onChange={(e) => setChosenWeek(e.target.value)} /></label><button>Buka minggu</button>
        </form>
        {!editing && <div className="filters">
            <label>Tampilan<select value={singleDay} onChange={(e) => setSingleDay(e.target.value)}><option value="">Mingguan</option>{dates.map((date) => <option key={date} value={date}>Harian · {day(date)}</option>)}</select></label>
            <label>Posisi<select value={position} onChange={(e) => setPosition(e.target.value)}><option value="">Semua posisi</option>{[...new Map(members.map((item) => [item.position, item.position_name]))].map(([code, name]) => <option key={code} value={code}>{name}</option>)}</select></label>
            <label>Karyawan<input type="search" value={memberName} onChange={(e) => setMemberName(e.target.value)} /></label>
        </div>}
        <section className="card"><h2>{day(week)} – {day(dates[6])}</h2>
            {!version && <Empty>Jadwal resmi minggu ini belum tersimpan.</Empty>}
            <p>Pagi 07.00–15.00 · Sore 15.00–23.00</p>
            <form onSubmit={submit}>
                {(version > 0 || editing) && <div className="scroll" tabIndex={0} role="region" aria-label="Grid jadwal mingguan"><table>
                    <thead><tr><th scope="col">Karyawan</th>{visibleDates.map((date) => <th key={date} scope="col">{day(date)}</th>)}</tr></thead>
                    <tbody>{visibleMembers.map((member) => <tr className={member.id === account?.id ? 'mine' : ''} key={member.id}>
                        <th scope="row">{member.name}<small>{member.position_name}{member.id === account?.id ? ' · Kamu' : ''}</small></th>
                        {visibleDates.map((date) => {
                            const stored = current.get(member.id + ':' + date)?.shift;
                            const shift = value(member.id, date);
                            const inactive = Boolean(member.inactive && date >= member.inactive);
                            const past = date < localTime.slice(0, 10) || (date === localTime.slice(0, 10) && ((stored === 'pagi' && localTime.slice(11) >= '07:00') || (stored === 'sore' && localTime.slice(11) >= '15:00')));
                            return <td key={date}>{date < member.joined ? 'Belum bergabung' : inactive ?
                                <span className={stored && stored !== 'libur' ? 'warn' : ''}>{stored && stored !== 'libur' ? shifts[stored] + ' · Perlu pengganti' : 'Nonaktif'}</span> :
                                editing && !past ? <select aria-label={member.name + ', ' + day(date)} value={shift} onChange={(e) => form.setData('cells', { ...form.data.cells, [member.id]: { ...form.data.cells[member.id], [date]: e.target.value } })}>
                                    <option value="" disabled>Pilih shift</option><option value="pagi">Pagi</option><option value="sore">Sore</option><option value="libur">Libur</option>
                                </select> : shifts[shift] ?? 'Belum diisi'}</td>;
                        })}
                    </tr>)}</tbody>
                    {editing && <tfoot>{['pagi', 'sore'].map((shift) => <tr key={shift}><th scope="row">{shift === 'pagi' ? 'Pagi' : 'Sore'} · Kasir/Baker</th>{dates.map((date) => {
                        const coverage = members.filter((member) => date >= member.joined && (!member.inactive || date < member.inactive) && value(member.id, date) === shift);
                        const cashier = coverage.filter((member) => member.position === 'KASIR').length;
                        const baker = coverage.filter((member) => member.position === 'BAKER').length;
                        return <td key={date} className={cashier < 1 || baker < 2 ? 'warn' : ''}>{cashier}/{baker}{(cashier < 1 || baker < 2) && ' · Kurang'}</td>;
                    })}</tr>)}</tfoot>}
                </table></div>}
                {(version > 0 || editing) && !visibleMembers.length && <Empty>Tidak ada karyawan yang cocok dengan filter.</Empty>}
                {editing && <>
                    {version > 0 && <label>Alasan revisi<textarea required maxLength={1000} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} /></label>}
                    {conflicts.length > 0 && <p className="warn">Revisi berdampak pada pengajuan {conflicts.map((id) => '#' + id).join(', ')}.</p>}
                    <div className="actions"><button className="primary" disabled={form.processing || !Object.keys(form.data.cells).length}>{form.processing ? 'Menyimpan…' : 'Simpan jadwal'}</button>
                        <button type="button" onClick={() => { if (!form.isDirty || window.confirm('Buang perubahan yang belum disimpan?')) { form.reset(); setEditing(false); } }}>Batal edit</button></div>
                </>}
            </form>
        </section>
    </Layout>;
}
