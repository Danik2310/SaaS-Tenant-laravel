import { useState, useEffect } from 'react';
import axios from 'axios';

export default function useAuth() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        axios.get('/admin/user')
            .then(res => setUser(res.data.user || null))
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);

    return { user, loading, setUser };
}