import { defineStore } from 'pinia';
import { auth } from '../api/client.js';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('ph_user') || 'null'),
    }),
    getters: {
        isLoggedIn: (state) => !!state.user,
    },
    actions: {
        async login(email, password) {
            const data = await auth.login(email, password);
            this.user = data.user;
            localStorage.setItem('ph_user', JSON.stringify(data.user));
        },
        async logout() {
            await auth.logout();
            this.user = null;
            localStorage.removeItem('ph_user');
        },
    },
});
