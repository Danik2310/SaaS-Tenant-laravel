import axios from 'axios';

const api = axios.create({
    baseURL: '/',
    headers: {
        'Content-Type': 'application/json',
    },
    withCredentials: true, // include cookies for session-based auth
});

export default api;