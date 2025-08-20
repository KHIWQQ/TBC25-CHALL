import { ApiErrorResponse } from './types';

export class ApiClient {
  private baseUrl: string;

  constructor(baseUrl: string = process.env.REACT_APP_API_URL || '') {
    this.baseUrl = baseUrl;
  }

  public async post<T>(endpoint: string, data: FormData | object): Promise<T> {
    const url = `${this.baseUrl}${endpoint}`;
    const options: RequestInit = {
      method: 'POST',
      headers: data instanceof FormData ? {} : {
        'Content-Type': 'application/json',
      },
      body: data instanceof FormData ? data : JSON.stringify(data),
    };

    return this.makeRequest<T>(url, options);
  }

  public async get<T>(endpoint: string): Promise<T> {
    const url = `${this.baseUrl}${endpoint}`;
    const options: RequestInit = {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    };

    return this.makeRequest<T>(url, options);
  }

  private async makeRequest<T>(url: string, options: RequestInit): Promise<T> {
    try {
      const response = await fetch(url, options);
      const data = await response.json();

      if (!response.ok) {
        const errorData = data as ApiErrorResponse;
        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
      }

      return data as T;
    } catch (error) {
      if (error instanceof Error) {
        throw error;
      }
      throw new Error('Network error occurred');
    }
  }
}