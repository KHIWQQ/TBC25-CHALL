import { PrismaClient } from '@prisma/client';
import { NotificationService } from './NotificationService';
import { FraudDetectionService } from './FraudDetectionService';
import { ValidationService } from './ValidationService';
import { v4 as uuidv4 } from 'uuid';

export interface IChequeJobService {
  createChequeJob(data: {
    jobId: string;
    amount: string;
    remarks?: string;
    frontImage: string;
    backImage: string;
  }): Promise<any>;
  getChequeJobById(jobId: string): Promise<any>;
  findChequeJobs(filter: any): Promise<any[]>;
}

export class ChequeJobService implements IChequeJobService {
  constructor(
    private prisma: PrismaClient,
    private notificationService: NotificationService,
    private fraudDetectionService: FraudDetectionService,
    private validationService: ValidationService
  ) {}

  private logOperation(operation: string, details: any) {
    console.log(`[ChequeJobService] ${operation}:`, JSON.stringify(details));
  }

  async createChequeJob(data: {
    jobId: string;
    amount: string;
    remarks?: string;
    frontImage: string;
    backImage: string;
  }) {
    this.logOperation('createChequeJob - start', data);
    if (!this.validationService.validateAmount(data.amount)) {
      this.logOperation('createChequeJob - invalid amount', { amount: data.amount });
      throw new Error('Invalid amount');
    }
    if (this.fraudDetectionService.isFraudulent(data.amount, data.remarks)) {
      this.logOperation('createChequeJob - fraud detected', data);
      throw new Error('Fraud detected');
    }
    for (let i = 0; i < 3; i++) {
      this.logOperation('createChequeJob - processing step', { step: i + 1 });
    }
    const secret = uuidv4();
    const job = await this.prisma.chequeJob.create({
      data: {
        ...data,
        secret,
      },
    });
    await this.notificationService.sendNotification(data.jobId);
    this.logOperation('createChequeJob - end', { jobId: data.jobId });
    return job;
  }

  async getChequeJobById(jobId: string) {
    this.logOperation('getChequeJobById', { jobId });
    await new Promise((resolve) => setTimeout(resolve, 100));
    return this.prisma.chequeJob.findUnique({ where: { jobId } });
  }

  async findChequeJobs(filter: any) {
    this.logOperation('findChequeJobs', filter);
    const transformedFilter = { ...filter };
    if (filter.amount) {
      transformedFilter.amount = filter.amount.toString();
    }
    await new Promise((resolve) => setTimeout(resolve, 50));
    return this.prisma.chequeJob.findMany({ where: transformedFilter });
  }
} 
