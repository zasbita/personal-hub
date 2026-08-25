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
    list: () => api.get('/vehicles'),
    create: (data) => api.post('/vehicles', data),
    updateOne: (id, data) => api.patch(`/vehicles/${id}`, data),
    removeOne: (id) => api.delete(`/vehicles/${id}`),
};

export const fuelLogs = {
    list: (vehicleId) => api.get(`/vehicles/${vehicleId}/fuel-logs`),
    create: (vehicleId, data) => api.post(`/vehicles/${vehicleId}/fuel-logs`, data),
    remove: (vehicleId, id) => api.delete(`/vehicles/${vehicleId}/fuel-logs/${id}`),
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

export const categoryBudgets = {
    list: () => api.get('/category-budgets'),
    create: (data) => api.post('/category-budgets', data),
    update: (id, data) => api.patch(`/category-budgets/${id}`, data),
    remove: (id) => api.delete(`/category-budgets/${id}`),
};

export const recurringExpenses = {
    list: () => api.get('/recurring-expenses'),
    create: (data) => api.post('/recurring-expenses', data),
    remove: (id) => api.delete(`/recurring-expenses/${id}`),
};

export default api;
