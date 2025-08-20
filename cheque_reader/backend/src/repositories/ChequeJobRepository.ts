import { PrismaClient } from '@prisma/client';

export class ChequeJobRepository {
  constructor(private prisma: PrismaClient) {}

  async create(data: {
    jobId: string;
    amount: string;
    remarks?: string;
    frontImage: string;
    backImage: string;
    secret: string;
  }) {
    return this.prisma.chequeJob.create({ data });
  }

  async findUniqueByJobId(jobId: string) {
    return this.prisma.chequeJob.findUnique({ where: { jobId } });
  }

  async findMany(filter: any) {
    return this.prisma.chequeJob.findMany({ where: filter });
  }
} 