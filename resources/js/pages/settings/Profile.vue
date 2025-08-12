<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import TopNav from '@/components/TopNav.vue';
import { type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const page = usePage();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
  <Head title="Profile settings" />

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
          <div class="flex flex-col space-y-6">
            <HeadingSmall title="Profile information" description="Update your name and email address" />

            <form @submit.prevent="submit" class="space-y-6">
              <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Full name" />
                <InputError class="mt-2" :message="form.errors.name" />
              </div>

              <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                  id="email"
                  type="email"
                  class="mt-1 block w-full"
                  v-model="form.email"
                  required
                  autocomplete="email"
                  placeholder="Email address"
                />
                <InputError class="mt-2" :message="form.errors.email" />
              </div>

              <div v-if="mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-white/80">
                  Your email address is unverified.
                  <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="text-white underline decoration-neutral-300/70 underline-offset-4 hover:decoration-white"
                  >
                    Click here to resend the verification email.
                  </Link>
                </p>

                <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-emerald-300">
                  A new verification link has been sent to your email address.
                </div>
              </div>

              <div class="flex items-center gap-4">
                <Button :disabled="form.processing">Save</Button>

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

          <DeleteUser />
        </SettingsLayout>
      </section>
    </main>
  </div>
</template>
