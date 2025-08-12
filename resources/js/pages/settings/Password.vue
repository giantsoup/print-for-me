<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import TopNav from '@/components/TopNav.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: any) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                if (passwordInput.value instanceof HTMLInputElement) {
                    passwordInput.value.focus();
                }
            }

            if (errors.current_password) {
                form.reset('current_password');
                if (currentPasswordInput.value instanceof HTMLInputElement) {
                    currentPasswordInput.value.focus();
                }
            }
        },
    });
};
</script>

<template>
  <Head title="Password settings" />

  <div class="relative min-h-screen overflow-hidden text-white">
    <!-- Synthwave background -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-gradient-to-br from-[#0b002b] via-[#12002f] to-[#340058]" />
      <div class="absolute inset-x-0 bottom-0 h-1/2 [background:radial-gradient(80%_50%_at_50%_120%,rgba(255,0,204,0.6),transparent_70%)]" />
      <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(255,255,255,.09)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.09)_1px,transparent_1px)]; [background-size:40px_40px]; [background-position:center]" />
    </div>

    <!-- Top navigation -->
    <TopNav />

    <!-- Content -->
    <main class="relative z-10 mx-auto max-w-5xl px-6 pb-24 pt-6">
      <section class="rounded-lg border border-white/10 bg-white/5 p-6 backdrop-blur">
        <SettingsLayout>
          <div class="space-y-6">
            <HeadingSmall title="Update password" description="Ensure your account is using a long, random password to stay secure" />

            <form @submit.prevent="updatePassword" class="space-y-6">
              <div class="grid gap-2">
                <Label for="current_password">Current password</Label>
                <Input
                  id="current_password"
                  ref="currentPasswordInput"
                  v-model="form.current_password"
                  type="password"
                  class="mt-1 block w-full"
                  autocomplete="current-password"
                  placeholder="Current password"
                />
                <InputError :message="form.errors.current_password" />
              </div>

              <div class="grid gap-2">
                <Label for="password">New password</Label>
                <Input
                  id="password"
                  ref="passwordInput"
                  v-model="form.password"
                  type="password"
                  class="mt-1 block w-full"
                  autocomplete="new-password"
                  placeholder="New password"
                />
                <InputError :message="form.errors.password" />
              </div>

              <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <Input
                  id="password_confirmation"
                  v-model="form.password_confirmation"
                  type="password"
                  class="mt-1 block w-full"
                  autocomplete="new-password"
                  placeholder="Confirm password"
                />
                <InputError :message="form.errors.password_confirmation" />
              </div>

              <div class="flex items-center gap-4">
                <Button :disabled="form.processing">Save password</Button>

                <Transition
                  enter-active-class="transition ease-in-out"
                  enter-from-class="opacity-0"
                  leave-active-class="transition ease-in-out"
                  leave-to-class="opacity-0"
                >
                  <p v-show="form.recentlySuccessful" class="text-sm text-white/70">Saved.</p>
                </Transition>
              </div>
            </form>
          </div>
        </SettingsLayout>
      </section>
    </main>
  </div>
</template>
