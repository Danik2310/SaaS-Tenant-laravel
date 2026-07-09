import React from 'react';
import { render, renderHook } from '@testing-library/react';
import { ThemeProvider } from '@mui/material/styles';
import { MemoryRouter } from 'react-router-dom';
import theme from '../../resources/js/theme';

export function createMockApi() {
  const mockFn = () => {
    const fn = (...args) => fn.mockImplementation(...args);
    fn.mockResolvedValue = (v) => {
      fn.mockImplementation(() => Promise.resolve(v));
      return fn;
    };
    fn.mockResolvedValueOnce = (v) => {
      const orig = fn._mockOnce || [];
      orig.push(v);
      fn._mockOnce = orig;
      return fn;
    };
    fn.mockRejectedValueOnce = (v) => {
      const orig = fn._mockOnce || [];
      orig.push(Promise.reject(v));
      fn._mockOnce = orig;
      return fn;
    };
    fn.mockClear = () => {
      delete fn._mockOnce;
      fn._calls = [];
      return fn;
    };
    fn.mockRestore = () => {
      delete fn._mockOnce;
      fn._calls = [];
      return fn;
    };
    fn._calls = [];
    return fn;
  };

  const api = {
    get: mockFn(),
    post: mockFn(),
    put: mockFn(),
    patch: mockFn(),
    delete: mockFn(),
    interceptors: { request: { use: () => {} }, response: { use: () => {} } },
    defaults: {},
  };

  const mockApi = new Proxy(api, {
    get(target, prop) {
      if (prop in target) return target[prop];
      if (prop === 'default') return target;
      return undefined;
    },
  });

  return mockApi;
}

export function renderWithProviders(ui, { route = '/', ...options } = {}) {
  function Wrapper({ children }) {
    return (
      <MemoryRouter initialEntries={[route]}>
        <ThemeProvider theme={theme}>
          {children}
        </ThemeProvider>
      </MemoryRouter>
    );
  }

  return render(ui, { wrapper: Wrapper, ...options });
}

export function renderHookWithProviders(callback, options) {
  function Wrapper({ children }) {
    return (
      <MemoryRouter>
        <ThemeProvider theme={theme}>
          {children}
        </ThemeProvider>
      </MemoryRouter>
    );
  }

  return renderHook(callback, { wrapper: Wrapper, ...options });
}

export * from '@testing-library/react';
export { default as userEvent } from '@testing-library/user-event';
