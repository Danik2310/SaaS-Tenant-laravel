import { useState, useEffect } from 'react';
import axios from 'axios';

export default function useAuth() {
    const [user, setUser] = useState(null);
    const [permissions, setPermissions] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        axios.get('/admin/user')
            .then(res => {
                setUser(res.data.user || null);
                setPermissions(res.data.permissions || []);
            })
            .catch(() => {
                setUser(null);
                setPermissions([]);
            })
            .finally(() => setLoading(false));
    }, []);

    return { user, permissions, loading, setUser, setPermissions };
}