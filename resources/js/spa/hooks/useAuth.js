import { useQuery, useQueryClient } from '@tanstack/react-query';
import { login as loginApi, logout as logoutApi, getUser } from '../api/auth.js';
import { useCallback } from 'react';

export default function useAuth() {
    const queryClient = useQueryClient();

    const { data, isLoading, error } = useQuery({
        queryKey: ['auth', 'user'],
        queryFn: getUser,
        retry: false,
        staleTime: 5 * 60 * 1000,
    });

    const user = data?.data?.user ?? null;
    const isAuthenticated = !!user;

    const login = useCallback(async (email, password) => {
        const result = await loginApi(email, password);
        queryClient.invalidateQueries({ queryKey: ['auth'] });
        return result;
    }, [queryClient]);

    const logout = useCallback(async () => {
        await logoutApi();
        queryClient.clear();
        window.location.href = '/app/login';
    }, [queryClient]);

    return { user, isAuthenticated, isLoading, error, login, logout };
}
