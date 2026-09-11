declare global {
  interface Window {
    Ziggy: any;
  }

  var Ziggy: any;
}

// Allow globalThis to have any property
declare var globalThis: any;
