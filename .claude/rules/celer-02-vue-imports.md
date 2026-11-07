---
description: Import ordering and module alias conventions for app.rnc (Vue 3 / TS)
paths:
  - "app.rnc/**/*.vue"
  - "app.rnc/**/*.ts"
---
# Import Ordering (CRITICAL)

> **Auto-enforced by ESLint on save** (`perfectionist/sort-imports` + `@typescript-eslint/consistent-type-imports`). Running `npm run lint` will fix import order and `import type` automatically. You only need to manually verify the **No Component Imports** rule below.

Imports MUST follow this exact sequence without blank lines between groups. **Within each group, sort ASC alphabetically.**

## Groups (in order)

1. **Vue imports** (always first)

   ```typescript
   import { computed, ref, watch } from 'vue';
   ```

2. **Node modules** (pinia, axios, etc.)

   ```typescript
   import { defineStore } from 'pinia';
   ```

3. **Type imports** – Group ALL `import type` together, ASC alphabetical by imported name

   ```typescript
   import type { FormSubmitEvent } from '@primevue/forms';
   import type { PermissionGroup } from './interfaces';
   ```

4. **Internal dependencies** (non-type, ASC alphabetical by import path)

   ```typescript
   import { useAuth } from '@Composables';
   import { formatDate } from '@Helpers';
   ```

5. **Modules** (ASC alphabetical)

   ```typescript
   import { AuthForm } from '@AuthModule';
   ```

   **Always import from the module root alias — never from a sub-path (CRITICAL)**

   The module barrel (`index.ts` and `interfaces.ts` for shared types) re-exports everything that is public. Sub-paths bypass it.

   Enforced in app.rnc by ESLint `no-restricted-imports` (pattern `@AuthModule/**`).

   ❌ **NEVER** (any extra segment after `@AuthModule`):

   ```typescript
   import { VerifyLoginCodeMockService } from '@AuthModule/services';
   import { USER_ROLE } from '@AuthModule/enums';
   import type { PermissionGroup } from '@AuthModule/services/getPermissionGroups.service';
   import type { Profile } from '@AuthModule/views/RolesAndPermissions/interfaces';
   ```

   ✅ **ALWAYS:**

   ```typescript
   import { VerifyLoginCodeMockService } from '@AuthModule';
   import { USER_ROLE } from '@AuthModule';
   import type { PermissionGroup, Profile } from '@AuthModule';
   ```

   If a type or symbol is missing from `@AuthModule`, add it to `src/modules/Auth/interfaces.ts` or the module `index.ts` barrel — **never** import the defining file by path.

6. **Local imports** (relative paths, ASC alphabetical by path)

   When mixing internal (@Helpers, @Composables) with local (../../enums, ../../services): **Internal first, then local.** Within local, sort by path ASC (e.g. enums before services).

   ```typescript
   import { translateError } from '@Helpers';
   import { FORM_FIELD_TYPE } from '../../enums';
   import { GetFormService } from '../../services';
   ```

## No Component Imports (CRITICAL)

This project uses `unplugin-vue-components` (vite.config.ts). Components from `src/modules/*`, `src/components/*`, `src/layouts/*` are **auto-imported**. PrimeVue components are also auto-imported via PrimeVueResolver.

**Do NOT import Vue components.** Use them directly in the template.

❌ **NEVER:**

```typescript
import { MFormFieldConfig } from '../MFormFieldConfig';
import { OFormRenderer } from '../OFormRenderer';
```

✅ **ALWAYS:** Use the component in template; no import.
