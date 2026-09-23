import { describe, expect, it, vi } from 'vitest';

import { submitAppointmentRequest } from '../../lib/queue-appointments';

describe('submitAppointmentRequest', () => {
    it('submits a new appointment with the router still bound', () => {
        const navigation = {
            patch: vi.fn(),
            post: vi.fn(),
        };
        const payload = { customer_name: 'Customer' };
        const options = { preserveScroll: true };

        submitAppointmentRequest(navigation, false, '/team/queue/appointments', payload, options);

        expect(navigation.post).toHaveBeenCalledWith('/team/queue/appointments', payload, options);
        expect(navigation.patch).not.toHaveBeenCalled();
    });

    it('submits an edited appointment through patch', () => {
        const navigation = {
            patch: vi.fn(),
            post: vi.fn(),
        };
        const payload = { customer_name: 'Updated customer' };
        const options = { preserveScroll: true };

        submitAppointmentRequest(navigation, true, '/team/queue/appointments/42', payload, options);

        expect(navigation.patch).toHaveBeenCalledWith('/team/queue/appointments/42', payload, options);
        expect(navigation.post).not.toHaveBeenCalled();
    });
});
