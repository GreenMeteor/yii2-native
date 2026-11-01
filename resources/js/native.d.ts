export interface NativeConfig {
  bridge: string;
  events: string;
  secret: string | null;
  timeout: number;
  csrfToken: string | null;
}

export class NativeError extends Error {
  type: string;
  status: number;
}

export type NativeHandler = (payload: Record<string, any>, event: string) => void;
export type NativeApi = Record<string, (params?: Record<string, any>) => Promise<any>>;

export function configure(options: Partial<NativeConfig>): void;
export function call<T = any>(method: string, params?: Record<string, any>): Promise<T>;
export function emit<T = any>(event: string, payload?: Record<string, any>, id?: string | null): Promise<T>;
export function on(event: string, handler: NativeHandler): () => void;
export function off(event: string, handler: NativeHandler): void;
export function once(event: string, handler: NativeHandler): () => void;

export interface NativeClient {
  call: typeof call;
  emit: typeof emit;
  on: typeof on;
  off: typeof off;
  once: typeof once;
  configure: typeof configure;
  NativeError: typeof NativeError;
  biometrics: NativeApi; browser: NativeApi; camera: NativeApi; clipboard: NativeApi;
  device: NativeApi; dialog: NativeApi; filesystem: NativeApi; geolocation: NativeApi;
  haptics: NativeApi; microphone: NativeApi; network: NativeApi; notifications: NativeApi;
  scanner: NativeApi; secureStorage: NativeApi; share: NativeApi; system: NativeApi;
  [plugin: string]: any;
}

export const Native: NativeClient;
export default Native;
