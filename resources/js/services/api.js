import axios from 'axios';

const api = axios.create({
    baseURL: '/',
    headers: {
        'Content-Type': 'application/json',
    },
    withCredentials: true, // include cookies for session-based auth
});

api.interceptors.response.use(
    (response) => {
        if (response.data && typeof response.data.data === 'object' && !Array.isArray(response.data.data)) {
            // Promote nested data keys for consistency across API responses
            const meta = response.data.meta;
            Object.assign(response.data, response.data.data);
            delete response.data.data;
            if (meta) response.data.meta = meta;
        }
        return response;
    },
    (error) => {
        if (error.response?.status === 403 && error.response?.data?.type !== 'plan_limit' && !error.config?.bypass403Redirect) {
            const message = error.response?.data?.message;
            const query = message ? `?message=${encodeURIComponent(message)}` : '';
            window.location.assign(`/admin/unauthorized${query}`);
        }
        return Promise.reject(error);
    }
);

export default api;