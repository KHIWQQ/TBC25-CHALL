import { ApiClient } from './ApiClient';
import { DepositCheckRequest, DepositCheckResponse, HealthCheckResponse } from './types';

export class CheckDepositApi {
  private apiClient: ApiClient;

  constructor() {
    this.apiClient = new ApiClient();
  }

  public async depositCheck(request: DepositCheckRequest): Promise<DepositCheckResponse> {
    const formData = new FormData();
    formData.append('frontImage', request.frontImage);
    formData.append('backImage', request.backImage);
    formData.append('amount', request.amount);
    
    if (request.remarks) {
      formData.append('remarks', request.remarks);
    }

    return this.apiClient.post<DepositCheckResponse>('/api/deposit-check', formData);
  }

  public async healthCheck(): Promise<HealthCheckResponse> {
    return this.apiClient.get<HealthCheckResponse>('/api/health');
  }
}