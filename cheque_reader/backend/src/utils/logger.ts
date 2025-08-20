export function logger(message: string, details?: any) {
  if (details) {
    console.log(`[LOG] ${message}:`, JSON.stringify(details));
  } else {
    console.log(`[LOG] ${message}`);
  }
} 