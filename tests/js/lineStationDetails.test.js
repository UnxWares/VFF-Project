import { describe, expect, it } from 'vitest';
import { withStationDetails } from '../../resources/js/lib/lineStationDetails.js';

describe('withStationDetails', () => {
    const stations = [
        { slug: 'paris', name: 'Paris', lng: 2.37, lat: 48.84, eras: { 2030: 'active' } },
        { slug: 'melun', name: 'Melun', lng: 2.65, lat: 48.53, eras: { 2030: 'active' } },
    ];

    it('waits for the deferred details', () => {
        expect(withStationDetails(stations, undefined)).toBeNull();
    });

    it('joins each station to its details by slug, in the order of the first display', () => {
        const rows = withStationDetails(stations, [
            { slug: 'melun', pk_label: '44+500', city: 'Melun' },
            { slug: 'paris', pk_label: '0+000', city: 'Paris' },
        ]);
        expect(rows.map((s) => [s.slug, s.pk_label, s.city, s.lng])).toEqual([
            ['paris', '0+000', 'Paris', 2.37],
            ['melun', '44+500', 'Melun', 2.65],
        ]);
    });

    it('keeps a station without details', () => {
        expect(withStationDetails(stations, [])).toEqual(stations);
    });
});
