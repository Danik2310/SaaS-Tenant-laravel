import { createContext, useContext, useMemo } from 'react';
import useAuth from '../hooks/useAuth';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const auth = useAuth();

  const value = useMemo(() => auth, [auth.user, auth.permissions, auth.loading]);

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuthContext() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuthContext must be used within an AuthProvider');
  }
  return context;
}
