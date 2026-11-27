import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';

export type Account = { id: number; employee_code: string; name: string; role: 'admin' | 'supervisor' | 'employee'; must_change_password: boolean };
export type Outlet = { id: number; code: string; name: string; timezone: string; active: boolean | number };
export type PageData<T> = { data: T[]; prev_page_url: string | null; next_page_url: string | null };
export type Change = { name: string; date: string; before: string | null; after: string; position?: string };
type Shared = { account: Account | null; unread: number; message?: string; errors: Record<string, string> };
export const shifts: Record<string, string> = { pagi: 'Pagi · 07.00–15.00', sore: 'Sore · 15.00–23.00', libur: 'Libur' };
export const roles = { employee: 'Karyawan', supervisor: 'SPV', admin: 'Admin' };
export const states: Record<string, string> = {
    pending_peer: 'Menunggu rekan', pending_supervisor: 'Menunggu SPV', approved: 'Disetujui',
    rejected: 'Ditolak', expired: 'Kedaluwarsa', withdrawn: 'Dibatalkan peminta',
    invalidated: 'Tidak berlaku karena perubahan jadwal', reversed: 'Dibatalkan setelah persetujuan',
};
export const day = (value: string) => new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(value + 'T00:00:00Z'));
export function time(value: string, timezone = 'Asia/Jakarta') {
    const input = value.includes('T') ? value : value.replace(' ', 'T') + 'Z';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'long', timeZone: timezone }).format(new Date(input));
}
export const useShared = () => usePage<Shared>().props;

export function Layout({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    const { account, message, errors, unread } = useShared();
    const { url } = usePage();
    const [open, setOpen] = useState(false);
    useEffect(() => {
        if (!account || account.must_change_password) return;
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: ['unread'], showProgress: false });
        }, 45_000);
        return () => window.clearInterval(timer);
    }, [account?.id, account?.must_change_password]);
    const links = account?.role === 'admin' ? [['/', 'Dashboard'], ['/admin', 'Administrasi']]
        : [['/', 'Dashboard'], ['/jadwal', 'Jadwal'], ['/pertukaran', account?.role === 'supervisor' ? 'Persetujuan' : 'Pertukaran'], ['/riwayat', 'Riwayat & konfirmasi']];
    return <div className="shell">
        <Head title={title} />
        <a className="skip" href="#main">Ke konten utama</a>
        <header className="sidebar">
            <Link href="/" className="brand" translate="no">Roti’O<span>Shift Management</span></Link>
            {account && <button className="menu" type="button" aria-expanded={open} aria-controls="main-navigation" onClick={() => setOpen(!open)}>Menu</button>}
            {account && <nav id="main-navigation" className={open ? 'nav open' : 'nav'} aria-label="Navigasi utama">
                {!account.must_change_password && [...links, ['/notifikasi', unread ? 'Notifikasi (' + unread + ')' : 'Notifikasi'], ['/password', 'Profil & password']].map(([href, label]) =>
                    <Link key={href} href={href} aria-current={(url.split('?')[0] === href || (href !== '/' && url.startsWith(href + '/'))) ? 'page' : undefined} onClick={() => setOpen(false)}>{label}</Link>)}
                <Link href="/keluar" method="post" as="button" onClick={() => setOpen(false)}>Keluar</Link>
            </nav>}
            {account && <div className="identity"><strong>{account.name}</strong><span>{roles[account.role]} · {account.employee_code}</span></div>}
        </header>
        <main id="main" tabIndex={-1}>
            <div className="intro"><h1>{title}</h1>{description && <p>{description}</p>}</div>
            {message && <div role="status" className="alert success">{message}</div>}
            {Object.keys(errors).length > 0 && <ul role="alert" className="alert errors">{Object.entries(errors).map(([key, error]) => <li key={key}>{error}</li>)}</ul>}
            {children}
        </main>
    </div>;
}

export function Pagination<T>({ page }: { page: PageData<T> }) {
    return <nav className="actions" aria-label="Halaman hasil">
        {page.prev_page_url && <Link href={page.prev_page_url}>Sebelumnya</Link>}
        {page.next_page_url && <Link href={page.next_page_url}>Berikutnya</Link>}
    </nav>;
}

export function Compare({ rows, reverse = false }: { rows: Change[]; reverse?: boolean }) {
    return <div className="scroll"><table>
        <thead><tr><th scope="col">Karyawan</th><th scope="col">Tanggal</th><th scope="col">Sebelum</th><th scope="col">Sesudah</th></tr></thead>
        <tbody>{rows.map((row, index) => <tr key={index}><th scope="row">{row.name}{row.position && <small>{row.position}</small>}</th><td>{day(row.date)}</td>
            <td>{shifts[(reverse ? row.after : row.before) ?? ''] ?? 'Belum dijadwalkan'}</td>
            <td>{shifts[(reverse ? row.before : row.after) ?? ''] ?? 'Belum dijadwalkan'}</td></tr>)}</tbody>
    </table></div>;
}

export function Empty({ children }: { children: ReactNode }) {
    return <p className="empty">{children}</p>;
}
