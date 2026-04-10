// File: resources/js/components/TableSkeleton.jsx
// Skeleton reutilizable para tablas — mejora LCP al mostrar contenido inmediatamente

import React from 'react';

/**
 * @param {number}   rows    - cantidad de filas skeleton (default 6)
 * @param {number[]} widths  - % de ancho de cada columna (default [40,20,20,20])
 * @param {boolean}  avatar  - mostrar círculo avatar en primera celda
 * @param {boolean}  badge   - mostrar badge en última celda
 */
export default function TableSkeleton({ rows = 6, widths = [40, 20, 20, 20], avatar = false, badge = false }) {
    return (
        <table className="skeleton-table" aria-hidden="true">
            <tbody>
                {Array.from({ length: rows }).map((_, r) => (
                    <tr key={r} className="skeleton-row" style={{ borderBottom: '1px solid #f5f5f5' }}>
                        {widths.map((w, c) => (
                            <td key={c} className="skeleton-cell">
                                {c === 0 && avatar ? (
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                        <span className="skeleton-avatar" />
                                        <span className="skeleton-line" style={{ width: `${w}%`, flex: 1 }} />
                                    </div>
                                ) : c === widths.length - 1 && badge ? (
                                    <span className="skeleton-badge" />
                                ) : (
                                    <span
                                        className="skeleton-line"
                                        style={{
                                            width: `${w}%`,
                                            // variación aleatoria fija por fila para que no se vea uniforme
                                            opacity: 0.6 + ((r * 3 + c) % 5) * 0.08,
                                        }}
                                    />
                                )}
                            </td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
