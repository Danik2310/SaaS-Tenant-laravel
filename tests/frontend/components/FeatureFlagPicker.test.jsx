import { vi } from 'vitest';
import React from 'react';
import { fireEvent, screen } from '@testing-library/react';
import { renderWithProviders } from '../test-utils';
import FeatureFlagPicker from '@/modules/billing/FeatureFlagPicker';

const definitions = {
  advanced: { label: 'Advanced', description: 'Premium inventory features.' },
  api_access: { label: 'API Access', description: 'REST API access.' },
  custom_domain: { label: 'Custom Domain', description: 'Use your own domain.' },
};

describe('FeatureFlagPicker', () => {
  test('renders label and description for each feature', () => {
    renderWithProviders(<FeatureFlagPicker definitions={definitions} value={[]} onChange={() => {}} />);

    expect(screen.getByText('Advanced')).toBeInTheDocument();
    expect(screen.getByText('Premium inventory features.')).toBeInTheDocument();
    expect(screen.getByText('API Access')).toBeInTheDocument();
    expect(screen.getByText('Custom Domain')).toBeInTheDocument();
  });

  test('checks boxes that are in the value array', () => {
    renderWithProviders(<FeatureFlagPicker definitions={definitions} value={['advanced']} onChange={() => {}} />);

    const checkboxes = screen.getAllByRole('checkbox');
    expect(checkboxes[0]).toBeChecked();
    expect(checkboxes[1]).not.toBeChecked();
    expect(checkboxes[2]).not.toBeChecked();
  });

  test('adds key when an unchecked box is clicked', () => {
    const onChange = vi.fn();
    renderWithProviders(<FeatureFlagPicker definitions={definitions} value={[]} onChange={onChange} />);

    fireEvent.click(screen.getAllByRole('checkbox')[1]);

    expect(onChange).toHaveBeenCalledWith(['api_access']);
  });

  test('removes key when a checked box is clicked', () => {
    const onChange = vi.fn();
    renderWithProviders(<FeatureFlagPicker definitions={definitions} value={['advanced', 'api_access']} onChange={onChange} />);

    fireEvent.click(screen.getAllByRole('checkbox')[0]);

    expect(onChange).toHaveBeenCalledWith(['api_access']);
  });

  test('renders error message when provided', () => {
    renderWithProviders(
      <FeatureFlagPicker definitions={definitions} value={[]} onChange={() => {}} error="Unknown feature flag(s): mystery" />
    );

    expect(screen.getByText('Unknown feature flag(s): mystery')).toBeInTheDocument();
  });
});
