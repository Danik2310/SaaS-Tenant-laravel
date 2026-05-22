import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';

function HelloWorld() {
    return <div>Hello, World!</div>;
}

describe('Example', () => {
    it('renders hello world', () => {
        render(<HelloWorld />);
        expect(screen.getByText('Hello, World!')).toBeInTheDocument();
    });
});
