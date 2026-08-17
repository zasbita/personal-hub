import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: { 'Content-Type': 'application/json' },
});

api.interceptors.response.use(
    (res) => res.data,
    (err) => {
        if (err.response?.status === 401) {
            localStorage.removeItem('ph_user');
            window.location.href = '/login';
        }
        return Promise.reject(err);
    }
);

export const auth = {
    login: (email, password) => api.post('/auth/login', { email, password }),
    logout: () => api.post('/auth/logout'),
};

export const expenses = {
    list: (limit = 50) => api.get(`/expenses?limit=${limit}`),
    stats: () => api.get('/stats/expenses'),
    update: (id, data) => api.patch(`/expenses/${id}`, data),
    remove: (id) => api.delete(`/expenses/${id}`),
};

export const vehicle = {
    get: () => api.get('/vehicle'),
    update: (data) => api.patch('/vehicle', data),
};

export const matches = {
    list: () => api.get('/matches'),
    create: (data) => api.post('/matches', data),
};

export const preferences = {
    list: () => api.get('/preferences'),
    update: (id, enabled) => api.patch(`/preferences/${id}`, { notification_enabled: enabled }),
    remove: (id) => api.delete(`/preferences/${id}`),
};

export default api;
