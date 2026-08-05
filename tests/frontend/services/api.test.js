import { vi } from 'vitest';
import api from '@/services/api';

describe('api 403 response interceptor', () => {
  const originalLocation = window.location;
  let rejected;
  let assignSpy;

  beforeEach(() => {
    const locationStub = {
      href: '',
      assign: vi.fn(),
      replace: vi.fn(),
      reload: vi.fn(),
    };
    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      enumerable: true,
      value: locationStub,
    });
    assignSpy = locationStub.assign;
    const handlers = api.interceptors.response.handlers;
    rejected = handlers[handlers.length - 1].rejected;
  });

  afterEach(() => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      enumerable: true,
      value: originalLocation,
    });
  });

  const permissionError = {
    response: { status: 403, data: { message: 'User does not have the right permissions.' } },
  };

  test('full-page redirects to /admin/unauthorized on a permission 403', async () => {
    await expect(rejected(permissionError)).rejects.toEqual(permissionError);

    expect(assignSpy).toHaveBeenCalledTimes(1);
    expect(assignSpy).toHaveBeenCalledWith('/admin/unauthorized?message=User%20does%20not%20have%20the%20right%20permissions.');
  });

  test('does not redirect when the caller opts out with bypass403Redirect', async () => {
    const bypassError = {
      ...permissionError,
      config: { bypass403Redirect: true },
    };

    await expect(rejected(bypassError)).rejects.toEqual(bypassError);

    expect(assignSpy).not.toHaveBeenCalled();
  });

  test('does not redirect on a plan-limit 403', async () => {
    const planLimitError = {
      response: { status: 403, data: { message: 'You have reached the users limit of 5.', type: 'plan_limit' } },
    };

    await expect(rejected(planLimitError)).rejects.toEqual(planLimitError);

    expect(assignSpy).not.toHaveBeenCalled();
  });

  test('redirects without a message query when the 403 has no message', async () => {
    const bareError = { response: { status: 403, data: undefined } };

    await expect(rejected(bareError)).rejects.toEqual(bareError);

    expect(assignSpy).toHaveBeenCalledTimes(1);
    expect(assignSpy).toHaveBeenCalledWith('/admin/unauthorized');
  });

  test('does not redirect on non-403 statuses', async () => {
    const unauthenticatedError = { response: { status: 401, data: { message: 'Unauthenticated.' } } };

    await expect(rejected(unauthenticatedError)).rejects.toEqual(unauthenticatedError);

    expect(assignSpy).not.toHaveBeenCalled();
  });
});
