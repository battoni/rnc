import { http, HttpResponse } from 'msw';

const BASE = 'http://localhost';

// Default fixtures — override per-test with server.use(http.get(...))
export const mockUser = {
  id: 1,
  name: 'Alice',
  last_name: 'Smith',
  username: 'alice',
  role: 'Admin',
  role_slug: 'administrator',
  status: 'active',
  permissions: ['users.read', 'users.create', 'users.update', 'users.archive', 'users.delete'],
};

export const mockRole = {
  id: 1,
  name: 'Admin',
  slug: 'admin',
  description: 'Administrator role',
  users: [],
  permissionGroups: [
    {
      id: 'group-users',
      nameKey: 'permissions.users',
      icon: 'pi pi-user',
      permissions: [
        { key: 'users.read', labelKey: 'permissions.users.read', value: true },
        { key: 'users.create', labelKey: 'permissions.users.create', value: false },
      ],
    },
  ],
};

/** A Laravel `LengthAwarePaginator` payload around `rows` — the shape `usePaginatedList` reads. */
export function paginated<T>(rows: T[], { page = 1, perPage = 15, total = rows.length } = {}) {
  return {
    current_page: page,
    data: rows,
    first_page_url: '',
    from: rows.length ? (page - 1) * perPage + 1 : null,
    last_page: Math.max(1, Math.ceil(total / perPage)),
    last_page_url: '',
    links: [],
    next_page_url: null,
    path: '',
    per_page: perPage,
    prev_page_url: null,
    to: rows.length ? (page - 1) * perPage + rows.length : null,
    total,
  };
}

export const handlers = [
  // ---- Auth ----
  http.post(`${BASE}/auth/login`, () => HttpResponse.json({ data: mockUser })),

  http.get(`${BASE}/auth/me`, () => HttpResponse.json({ data: mockUser })),

  http.post(`${BASE}/auth/logout`, () => HttpResponse.json({})),

  // ---- Users ----
  // The list is paginated on the server: the rows ride in `data.data`, the counts beside them.
  http.get(`${BASE}/auth/users`, () => HttpResponse.json({ data: paginated([mockUser]) })),

  // Before `auth/users/:id`, which would otherwise match it: MSW answers with the FIRST match.
  http.get(`${BASE}/auth/users/check-username`, () => HttpResponse.json({ data: { available: true } })),

  http.get(`${BASE}/auth/users/:id`, ({ params }) =>
    HttpResponse.json({ data: { ...mockUser, id: Number(params.id) } })
  ),

  http.post(`${BASE}/auth/users`, async ({ request }) => {
    const body = (await request.json()) as Record<string, unknown>;
    return HttpResponse.json({ data: { ...mockUser, id: 99, ...body } }, { status: 201 });
  }),

  http.patch(`${BASE}/auth/users/:id`, async ({ params, request }) => {
    const body = (await request.json()) as Record<string, unknown>;
    return HttpResponse.json({ data: { ...mockUser, id: Number(params.id), ...body } });
  }),

  http.patch(`${BASE}/auth/users/:id/archive`, ({ params }) =>
    HttpResponse.json({
      data: { ...mockUser, id: Number(params.id), status: 'inactive' },
    })
  ),

  http.delete(`${BASE}/auth/users/:id`, ({ params }) =>
    HttpResponse.json({ data: { ...mockUser, id: Number(params.id) } })
  ),

  // ---- Roles ----
  http.get(`${BASE}/auth/roles`, () => HttpResponse.json({ data: [mockRole] })),

  http.get(`${BASE}/auth/roles/:id`, ({ params }) =>
    HttpResponse.json({ data: { ...mockRole, id: Number(params.id) } })
  ),

  http.patch(`${BASE}/auth/roles/:id/permissions`, async ({ params, request }) => {
    const permissions = (await request.json()) as Record<string, boolean>;
    const updatedGroups = mockRole.permissionGroups.map((group) => ({
      ...group,
      permissions: group.permissions.map((perm) => ({
        ...perm,
        value: permissions[perm.key] ?? perm.value,
      })),
    }));
    return HttpResponse.json({ data: { ...mockRole, id: Number(params.id), permissionGroups: updatedGroups } });
  }),

  // ---- Permission groups ----
  http.get(`${BASE}/auth/permission-groups`, () => HttpResponse.json({ data: mockRole.permissionGroups })),
];
