import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginPage.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: () => import('../views/DashboardLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', name: 'overview', component: () => import('../views/OverviewPage.vue') },
            { path: 'expenses', name: 'expenses', component: () => import('../views/ExpensesPage.vue') },
            { path: 'vehicle', name: 'vehicle', component: () => import('../views/VehiclePage.vue') },
            { path: 'sports', name: 'sports', component: () => import('../views/SportsPage.vue') },
            { path: 'settings', name: 'settings', component: () => import('../views/SettingsPage.vue') },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const user = localStorage.getItem('ph_user');
    if (to.meta.requiresAuth && !user) {
        next({ name: 'login' });
    } else if (to.meta.guest && user) {
        next({ name: 'overview' });
    } else {
        next();
    }
});

export default router;
