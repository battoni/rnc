<script setup lang="ts">
import { ref } from 'vue';
import { yupResolver } from '@primevue/forms/resolvers/yup';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import * as yup from 'yup';
import type { FormSubmitEvent } from '@primevue/forms';

type LongFormData = {
  address: string;
  bio: string;
  city: string;
  company: string;
  country: string;
  email: string;
  firstName: string;
  jobTitle: string;
  lastName: string;
  linkedIn: string;
  notes: string;
  phone: string;
  postalCode: string;
  state: string;
  website: string;
};

const emit = defineEmits<{
  onClose: [];
  onSuccess: [data: LongFormData];
}>();

const toast = useToast();
const { t } = useI18n();

const loading = ref(false);

const initialValues: LongFormData = {
  address: '',
  bio: '',
  city: '',
  company: '',
  country: '',
  email: '',
  firstName: '',
  jobTitle: '',
  lastName: '',
  linkedIn: '',
  notes: '',
  phone: '',
  postalCode: '',
  state: '',
  website: '',
};

const schema = yup.object().shape({
  address: yup.string().required('showcase.forms.errors.addressRequired'),
  bio: yup.string().optional(),
  city: yup.string().required('showcase.forms.errors.cityRequired'),
  company: yup.string().required('showcase.forms.errors.companyRequired'),
  country: yup.string().required('showcase.forms.errors.countryRequired'),
  email: yup.string().email('showcase.forms.errors.emailInvalid').required('showcase.forms.errors.emailRequired'),
  firstName: yup.string().required('showcase.forms.errors.firstNameRequired'),
  jobTitle: yup.string().required('showcase.forms.errors.jobTitleRequired'),
  lastName: yup.string().required('showcase.forms.errors.lastNameRequired'),
  linkedIn: yup.string().optional(),
  notes: yup.string().optional(),
  phone: yup.string().required('showcase.forms.errors.phoneRequired'),
  postalCode: yup.string().required('showcase.forms.errors.postalCodeRequired'),
  state: yup.string().optional(),
  website: yup.string().url('showcase.forms.errors.urlInvalid').optional(),
});

const resolver = yupResolver(schema);

// HELPERS
function getErrorMessage(fieldError: unknown): string {
  if (!fieldError) return '';
  if (typeof fieldError === 'string') return fieldError;
  if (
    fieldError &&
    typeof fieldError === 'object' &&
    'message' in fieldError &&
    typeof (fieldError as { message: unknown }).message === 'string'
  ) {
    return (fieldError as { message: string }).message;
  }
  return String(fieldError);
}

// EVENTS
function onSubmit(event: FormSubmitEvent) {
  if (!event.valid) return;

  loading.value = true;

  const payload: LongFormData = {
    address: String(event.values?.address ?? ''),
    bio: String(event.values?.bio ?? ''),
    city: String(event.values?.city ?? ''),
    company: String(event.values?.company ?? ''),
    country: String(event.values?.country ?? ''),
    email: String(event.values?.email ?? ''),
    firstName: String(event.values?.firstName ?? ''),
    jobTitle: String(event.values?.jobTitle ?? ''),
    lastName: String(event.values?.lastName ?? ''),
    linkedIn: String(event.values?.linkedIn ?? ''),
    notes: String(event.values?.notes ?? ''),
    phone: String(event.values?.phone ?? ''),
    postalCode: String(event.values?.postalCode ?? ''),
    state: String(event.values?.state ?? ''),
    website: String(event.values?.website ?? ''),
  };

  Promise.resolve(payload)
    .then((data) => {
      emit('onSuccess', data);
      toast.add({ severity: 'success', summary: t('showcase.forms.itemCreated'), life: 3000 });
    })
    .catch(() => toast.add({ severity: 'error', summary: t('errors.somethingWentWrong'), life: 5000 }))
    .finally(() => (loading.value = false));
}
</script>

<template>
  <Form
    id="showcase-long-form"
    class="mx-auto flex w-full max-w-[500px] flex-col gap-4 pb-[calc(5rem+env(safe-area-inset-bottom,0px))]"
    :initialValues
    :resolver
    @submit="onSubmit"
  >
    <FormField name="firstName">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="firstName"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.firstName') }}
          </label>

          <InputText
            fluid
            id="firstName"
            name="firstName"
            :placeholder="$t('showcase.forms.placeholders.firstName')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="lastName">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="lastName"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.lastName') }}
          </label>

          <InputText
            fluid
            id="lastName"
            name="lastName"
            :placeholder="$t('showcase.forms.placeholders.lastName')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="email">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="email"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.email') }}
          </label>

          <InputText
            fluid
            id="email"
            name="email"
            placeholder="you@example.com"
            type="email"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="phone">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="phone"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.phone') }}
          </label>

          <InputText
            fluid
            id="phone"
            name="phone"
            type="tel"
            :placeholder="$t('showcase.forms.placeholders.phone')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="company">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="company"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.company') }}
          </label>

          <InputText
            fluid
            id="company"
            name="company"
            :placeholder="$t('showcase.forms.placeholders.company')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="jobTitle">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="jobTitle"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.jobTitle') }}
          </label>

          <InputText
            fluid
            id="jobTitle"
            name="jobTitle"
            :placeholder="$t('showcase.forms.placeholders.jobTitle')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="website">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="website"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.website') }}
          </label>

          <InputText
            fluid
            id="website"
            name="website"
            placeholder="https://example.com"
            type="url"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="address">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="address"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.address') }}
          </label>

          <InputText
            fluid
            id="address"
            name="address"
            :placeholder="$t('showcase.forms.placeholders.address')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="city">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="city"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.city') }}
          </label>

          <InputText
            fluid
            id="city"
            name="city"
            :placeholder="$t('showcase.forms.placeholders.city')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="state">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="state"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.state') }}
          </label>

          <InputText
            fluid
            id="state"
            name="state"
            :placeholder="$t('showcase.forms.placeholders.state')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="country">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="country"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.country') }}
          </label>

          <InputText
            fluid
            id="country"
            name="country"
            :placeholder="$t('showcase.forms.placeholders.country')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="postalCode">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="postalCode"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.postalCode') }}
          </label>

          <InputText
            fluid
            id="postalCode"
            name="postalCode"
            :placeholder="$t('showcase.forms.placeholders.postalCode')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="bio">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="bio"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.bio') }}
          </label>

          <Textarea
            autoResize
            fluid
            id="bio"
            name="bio"
            rows="4"
            :placeholder="$t('showcase.forms.placeholders.bio')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="linkedIn">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="linkedIn"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            LinkedIn
          </label>

          <InputText
            fluid
            id="linkedIn"
            name="linkedIn"
            placeholder="linkedin.com/in/username"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>

    <FormField name="notes">
      <template #default="{ error: fieldError }">
        <div class="flex flex-col gap-2">
          <label
            for="notes"
            :class="['mb-2 block text-sm', fieldError ? 'text-danger-700' : 'text-muted']"
          >
            {{ $t('showcase.forms.fields.notes') }}
          </label>

          <Textarea
            autoResize
            fluid
            id="notes"
            name="notes"
            rows="3"
            :placeholder="$t('showcase.forms.placeholders.notes')"
          />

          <small
            v-if="fieldError"
            class="text-danger-700 mt-1 block text-sm"
          >
            {{ $t(getErrorMessage(fieldError)) }}
          </small>
        </div>
      </template>
    </FormField>
  </Form>

  <div class="bg-canvas sticky bottom-0 z-10 mx-auto flex w-full max-w-[500px] shrink-0 justify-end gap-3 py-4">
    <Button
      severity="secondary"
      :label="$t('common.cancel')"
      @click="emit('onClose')"
    />

    <Button
      form="showcase-long-form"
      type="submit"
      :label="$t('common.save')"
      :loading
    />
  </div>

  <div class="bg-canvas absolute bottom-0 left-0 z-10 h-5 w-full" />
</template>
