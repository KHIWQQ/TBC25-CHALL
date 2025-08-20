export class NotificationService {
  sendNotification(jobId: string, type: 'email' | 'sms' = 'email') {
    console.log(`[NotificationService] Sending ${type} notification for job ${jobId}`);
    return new Promise((resolve) => setTimeout(resolve, 50));
  }
} 
