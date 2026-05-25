import { vi } from 'vitest';
import React from 'react';
import { renderWithProviders, screen, fireEvent } from '../test-utils';
import ErrorBoundary from '@/Components/ErrorBoundary';

beforeEach(() => {
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

afterEach(() => {
  vi.restoreAllMocks();
});

function Bomb() {
  throw new Error('💥');
}

describe('ErrorBoundary', () => {
  test('renders children when no error', () => {
    renderWithProviders(
      <ErrorBoundary>
        <div>All good</div>
      </ErrorBoundary>
    );

    expect(screen.getByText('All good')).toBeInTheDocument();
  });

  test('renders fallback UI when child throws', () => {
    renderWithProviders(
      <ErrorBoundary>
        <Bomb />
      </ErrorBoundary>
    );

    expect(screen.getByText('Something went wrong')).toBeInTheDocument();
    expect(screen.getByText('This section failed to load. Please try refreshing the page.')).toBeInTheDocument();
    expect(screen.getByText('Reload Page')).toBeInTheDocument();
  });

  test('shows custom fallback message', () => {
    renderWithProviders(
      <ErrorBoundary fallbackMessage="Oops!">
        <Bomb />
      </ErrorBoundary>
    );

    expect(screen.getByText('Oops!')).toBeInTheDocument();
  });

  test('reload button calls window.location.reload', () => {
    const reloadSpy = vi.fn();
    Object.defineProperty(window, 'location', {
      value: { reload: reloadSpy },
      writable: true,
    });

    renderWithProviders(
      <ErrorBoundary>
        <Bomb />
      </ErrorBoundary>
    );

    fireEvent.click(screen.getByText('Reload Page'));
    expect(reloadSpy).toHaveBeenCalled();
  });
});
