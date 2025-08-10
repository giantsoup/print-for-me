// App-specific Inertia PageProps augmentation
// Ensures template access like $page.props.flash.status is properly typed

export {};

declare module '@inertiajs/core' {
  interface PageProps {
    flash?: {
      status?: string;
    };
  }
}
