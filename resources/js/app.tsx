import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { Dashboard, History, Login, Notifications, Password } from './pages/Information';
import Schedule from './pages/Schedule';
import Swaps from './pages/Swaps';
import Admin from './pages/Admin';
import '../css/app.css';

const pages = { Dashboard, History, Login, Notifications, Password, Schedule, Swaps, Admin };

void createInertiaApp({
    title: (title) => title ? title + ' | Roti’O' : 'Roti’O Shift Management',
    resolve: (name) => pages[name as keyof typeof pages],
    setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />),
});
