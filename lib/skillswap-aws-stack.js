"use strict";
var __createBinding = (this && this.__createBinding) || (Object.create ? (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    var desc = Object.getOwnPropertyDescriptor(m, k);
    if (!desc || ("get" in desc ? !m.__esModule : desc.writable || desc.configurable)) {
      desc = { enumerable: true, get: function() { return m[k]; } };
    }
    Object.defineProperty(o, k2, desc);
}) : (function(o, m, k, k2) {
    if (k2 === undefined) k2 = k;
    o[k2] = m[k];
}));
var __setModuleDefault = (this && this.__setModuleDefault) || (Object.create ? (function(o, v) {
    Object.defineProperty(o, "default", { enumerable: true, value: v });
}) : function(o, v) {
    o["default"] = v;
});
var __importStar = (this && this.__importStar) || (function () {
    var ownKeys = function(o) {
        ownKeys = Object.getOwnPropertyNames || function (o) {
            var ar = [];
            for (var k in o) if (Object.prototype.hasOwnProperty.call(o, k)) ar[ar.length] = k;
            return ar;
        };
        return ownKeys(o);
    };
    return function (mod) {
        if (mod && mod.__esModule) return mod;
        var result = {};
        if (mod != null) for (var k = ownKeys(mod), i = 0; i < k.length; i++) if (k[i] !== "default") __createBinding(result, mod, k[i]);
        __setModuleDefault(result, mod);
        return result;
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
exports.SkillswapAwsStack = void 0;
const cdk = __importStar(require("aws-cdk-lib"));
const ec2 = __importStar(require("aws-cdk-lib/aws-ec2"));
const rds = __importStar(require("aws-cdk-lib/aws-rds"));
const s3 = __importStar(require("aws-cdk-lib/aws-s3"));
const iam = __importStar(require("aws-cdk-lib/aws-iam"));
const elbv2 = __importStar(require("aws-cdk-lib/aws-elasticloadbalancingv2"));
const targets = __importStar(require("aws-cdk-lib/aws-elasticloadbalancingv2-targets"));
const cloudfront = __importStar(require("aws-cdk-lib/aws-cloudfront"));
const origins = __importStar(require("aws-cdk-lib/aws-cloudfront-origins"));
class SkillswapAwsStack extends cdk.Stack {
    constructor(scope, id, props) {
        super(scope, id, props);
        // 1. VPC (Network)
        // We use 0 NAT Gateways to keep it free.
        const vpc = new ec2.Vpc(this, 'SkillSwapVPC', {
            maxAzs: 2,
            natGateways: 0,
            subnetConfiguration: [
                { cidrMask: 24, name: 'Public', subnetType: ec2.SubnetType.PUBLIC },
                { cidrMask: 24, name: 'Private', subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS }
            ]
        });
        // 2. S3 Bucket (Assets)
        const bucket = new s3.Bucket(this, 'SkillSwapAssets', {
            removalPolicy: cdk.RemovalPolicy.DESTROY,
            autoDeleteObjects: true,
            publicReadAccess: true,
            blockPublicAccess: { blockPublicAcls: false, blockPublicPolicy: false, ignorePublicAcls: false, restrictPublicBuckets: false }
        });
        // 3. Database (RDS MySQL)
        // T3.MICRO is the modern Free Tier standard
        const db = new rds.DatabaseInstance(this, 'SkillSwapDB', {
            engine: rds.DatabaseInstanceEngine.mysql({ version: rds.MysqlEngineVersion.VER_8_0 }),
            vpc,
            vpcSubnets: { subnetType: ec2.SubnetType.PRIVATE_WITH_EGRESS }, // DB stays private for security
            instanceType: ec2.InstanceType.of(ec2.InstanceClass.T3, ec2.InstanceSize.MICRO),
            allocatedStorage: 20,
            databaseName: 'skillswap',
            credentials: rds.Credentials.fromGeneratedSecret('laravel'),
            multiAz: false,
            deletionProtection: false,
            removalPolicy: cdk.RemovalPolicy.DESTROY
        });
        // 4. EC2 Instance (App Server)
        const role = new iam.Role(this, 'AppRole', {
            assumedBy: new iam.ServicePrincipal('ec2.amazonaws.com'),
            managedPolicies: [
                iam.ManagedPolicy.fromAwsManagedPolicyName('AmazonSSMManagedInstanceCore'), // Required for Session Manager
                iam.ManagedPolicy.fromAwsManagedPolicyName('SecretsManagerReadWrite'), // To read DB password
                iam.ManagedPolicy.fromAwsManagedPolicyName('AmazonS3FullAccess') // To sync assets
            ]
        });
        const userData = ec2.UserData.forLinux();
        userData.addCommands('yum update -y', 'amazon-linux-extras install -y php8.1 nginx1', 'yum install -y git mysql', 'systemctl enable --now nginx');
        const instance = new ec2.Instance(this, 'AppInstance', {
            vpc,
            // FIXED: Using PUBLIC subnet so SSM works without NAT Gateway
            vpcSubnets: { subnetType: ec2.SubnetType.PUBLIC },
            instanceType: ec2.InstanceType.of(ec2.InstanceClass.T3, ec2.InstanceSize.MICRO),
            machineImage: ec2.MachineImage.latestAmazonLinux2(),
            role: role,
            userData: userData,
            // Allow public IP so it can reach the internet
            associatePublicIpAddress: true,
        });
        // Allow DB access from EC2
        db.connections.allowFrom(instance, ec2.Port.tcp(3306));
        // 5. Load Balancer & CloudFront
        const lb = new elbv2.ApplicationLoadBalancer(this, 'LB', {
            vpc,
            internetFacing: true
        });
        const listener = lb.addListener('Listener', { port: 80 });
        listener.addTargets('Target', {
            port: 80,
            targets: [new targets.InstanceTarget(instance)],
            healthCheck: { path: '/health-check.php' }
        });
        // Allow ALB to talk to EC2 on port 80
        instance.connections.allowFrom(lb, ec2.Port.tcp(80));
        const distribution = new cloudfront.Distribution(this, 'SkillSwapCF', {
            defaultBehavior: {
                origin: new origins.LoadBalancerV2Origin(lb),
                viewerProtocolPolicy: cloudfront.ViewerProtocolPolicy.ALLOW_ALL,
                allowedMethods: cloudfront.AllowedMethods.ALLOW_ALL,
                cachePolicy: cloudfront.CachePolicy.CACHING_DISABLED,
                originRequestPolicy: cloudfront.OriginRequestPolicy.ALL_VIEWER,
            },
        });
        // Outputs
        new cdk.CfnOutput(this, 'ALBEndpoint', { value: `http://${lb.loadBalancerDnsName}` });
        new cdk.CfnOutput(this, 'CloudFrontEndpoint', { value: `https://${distribution.distributionDomainName}` });
        new cdk.CfnOutput(this, 'BucketName', { value: bucket.bucketName });
        new cdk.CfnOutput(this, 'DBSecretArn', { value: db.secret?.secretArn || '' });
        new cdk.CfnOutput(this, 'InstanceId', { value: instance.instanceId });
    }
}
exports.SkillswapAwsStack = SkillswapAwsStack;
//# sourceMappingURL=data:application/json;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoic2tpbGxzd2FwLWF3cy1zdGFjay5qcyIsInNvdXJjZVJvb3QiOiIiLCJzb3VyY2VzIjpbInNraWxsc3dhcC1hd3Mtc3RhY2sudHMiXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6Ijs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7O0FBQUEsaURBQW1DO0FBRW5DLHlEQUEyQztBQUMzQyx5REFBMkM7QUFDM0MsdURBQXlDO0FBQ3pDLHlEQUEyQztBQUMzQyw4RUFBZ0U7QUFDaEUsd0ZBQTBFO0FBQzFFLHVFQUF5RDtBQUN6RCw0RUFBOEQ7QUFFOUQsTUFBYSxpQkFBa0IsU0FBUSxHQUFHLENBQUMsS0FBSztJQUM5QyxZQUFZLEtBQWdCLEVBQUUsRUFBVSxFQUFFLEtBQXNCO1FBQzlELEtBQUssQ0FBQyxLQUFLLEVBQUUsRUFBRSxFQUFFLEtBQUssQ0FBQyxDQUFDO1FBRXhCLG1CQUFtQjtRQUNuQix5Q0FBeUM7UUFDekMsTUFBTSxHQUFHLEdBQUcsSUFBSSxHQUFHLENBQUMsR0FBRyxDQUFDLElBQUksRUFBRSxjQUFjLEVBQUU7WUFDNUMsTUFBTSxFQUFFLENBQUM7WUFDVCxXQUFXLEVBQUUsQ0FBQztZQUNkLG1CQUFtQixFQUFFO2dCQUNuQixFQUFFLFFBQVEsRUFBRSxFQUFFLEVBQUUsSUFBSSxFQUFFLFFBQVEsRUFBRSxVQUFVLEVBQUUsR0FBRyxDQUFDLFVBQVUsQ0FBQyxNQUFNLEVBQUU7Z0JBQ25FLEVBQUUsUUFBUSxFQUFFLEVBQUUsRUFBRSxJQUFJLEVBQUUsU0FBUyxFQUFFLFVBQVUsRUFBRSxHQUFHLENBQUMsVUFBVSxDQUFDLG1CQUFtQixFQUFFO2FBQ2xGO1NBQ0YsQ0FBQyxDQUFDO1FBRUgsd0JBQXdCO1FBQ3hCLE1BQU0sTUFBTSxHQUFHLElBQUksRUFBRSxDQUFDLE1BQU0sQ0FBQyxJQUFJLEVBQUUsaUJBQWlCLEVBQUU7WUFDcEQsYUFBYSxFQUFFLEdBQUcsQ0FBQyxhQUFhLENBQUMsT0FBTztZQUN4QyxpQkFBaUIsRUFBRSxJQUFJ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QyxTQUFTLENBQUMsSUFBSSxFQUFFLG9CQUFvQixFQUFFLEVBQUUsS0FBSyxFQUFFLFdBQVcsWUFBWSxDQUFDLHNCQUFzQixFQUFFLEVBQUUsQ0FBQyxDQUFDO1FBQzNHLElBQUksR0FBRyxDQUFDLFNBQVMsQ0FBQyxJQUFJLEVBQUUsWUFBWSxFQUFFLEVBQUUsS0FBSyxFQUFFLE1BQU0sQ0FBQyxVQUFVLEVBQUUsQ0FBQyxDQUFDO1FBQ3BFLElBQUksR0FBRyxDQUFDLFNBQVMsQ0FBQyxJQUFJLEVBQUUsYUFBYSxFQUFFLEVBQUUsS0FBSyxFQUFFLEVBQUUsQ0FBQyxNQUFNLEVBQUUsU0FBUyxJQUFJLEVBQUUsRUFBRSxDQUFDLENBQUM7UUFDOUUsSUFBSSxHQUFHLENBQUMsU0FBUyxDQUFDLElBQUksRUFBRSxZQUFZLEVBQUUsRUFBRSxLQUFLLEVBQUUsUUFBUSxDQUFDLFVBQVUsRUFBRSxDQUFDLENBQUM7SUFDeEUsQ0FBQztDQUNGO0FBdkdELDhDQXVHQyIsInNvdXJjZXNDb250ZW50IjpbImltcG9ydCAqIGFzIGNkayBmcm9tICdhd3MtY2RrLWxpYic7XG5pbXBvcnQgeyBDb25zdHJ1Y3QgfSBmcm9tICdjb25zdHJ1Y3RzJztcbmltcG9ydCAqIGFzIGVjMiBmcm9tICdhd3MtY2RrLWxpYi9hd3MtZWMyJztcbmltcG9ydCAqIGFzIHJkcyBmcm9tICdhd3MtY2RrLWxpYi9hd3MtcmRzJztcbmltcG9ydCAqIGFzIHMzIGZyb20gJ2F3cy1jZGstbGliL2F3cy1zMyc7XG5pbXBvcnQgKiBhcyBpYW0gZnJvbSAnYXdzLWNkay1saWIvYXdzLWlhbSc7XG5pbXBvcnQgKiBhcyBlbGJ2MiBmcm9tICdhd3MtY2RrLWxpYi9hd3MtZWxhc3RpY2xvYWRiYWxhbmNpbmd2Mic7XG5pbXBvcnQgKiBhcyB0YXJnZXRzIGZyb20gJ2F3cy1jZGstbGliL2F3cy1lbGFzdGljbG9hZGJhbGFuY2luZ3YyLXRhcmdldHMnO1xuaW1wb3J0ICogYXMgY2xvdWRmcm9udCBmcm9tICdhd3MtY2RrLWxpYi9hd3MtY2xvdWRmcm9udCc7XG5pbXBvcnQgKiBhcyBvcmlnaW5zIGZyb20gJ2F3cy1jZGstbGliL2F3cy1jbG91ZGZyb250LW9yaWdpbnMnO1xuXG5leHBvcnQgY2xhc3MgU2tpbGxzd2FwQXdzU3RhY2sgZXh0ZW5kcyBjZGsuU3RhY2sge1xuICBjb25zdHJ1Y3RvcihzY29wZTogQ29uc3RydWN0LCBpZDogc3RyaW5nLCBwcm9wcz86IGNkay5TdGFja1Byb3BzKSB7XG4gICAgc3VwZXIoc2NvcGUsIGlkLCBwcm9wcyk7XG5cbiAgICAvLyAxLiBWUEMgKE5ldHdvcmspXG4gICAgLy8gV2UgdXNlIDAgTkFUIEdhdGV3YXlzIHRvIGtlZXAgaXQgZnJlZS5cbiAgICBjb25zdCB2cGMgPSBuZXcgZWMyLlZwYyh0aGlzLCAnU2tpbGxTd2FwVlBDJywge1xuICAgICAgbWF4QXpzOiAyLFxuICAgICAgbmF0R2F0ZXdheXM6IDAsXG4gICAgICBzdWJuZXRDb25maWd1cmF0aW9uOiBbXG4gICAgICAgIHsgY2lkck1hc2s6IDI0LCBuYW1lOiAnUHVibGljJywgc3VibmV0VHlwZTogZWMyLlN1Ym5ldFR5cGUuUFVCTElDIH0sXG4gICAgICAgIHsgY2lkck1hc2s6IDI0LCBuYW1lOiAnUHJpdmF0ZScsIHN1Ym5ldFR5cGU6IGVjMi5TdWJuZXRUeXBlLlBSSVZBVEVfV0lUSF9FR1JFU1MgfVxuICAgICAgXVxuICAgIH0pO1xuXG4gICAgLy8gMi4gUzMgQnVja2V0IChBc3NldHMpXG4gICAgY29uc3QgYnVja2V0ID0gbmV3IHMzLkJ1Y2tldCh0aGlzLCAnU2tpbGxTd2FwQXNzZXRzJywge1xuICAgICAgcmVtb3ZhbFBvbGljeTogY2RrLlJlbW92YWxQb2xpY3kuREVTVFJPWSxcbiAgICAgIGF1dG9EZWxldGVPYmplY3RzOiB0cnVlLFxuICAgICAgcHVibGljUmVhZEFjY2VzczogdHJ1ZSxcbiAgICAgIGJsb2NrUHVibGljQWNjZXNzOiB7IGJsb2NrUHVibGljQWNsczogZmFsc2UsIGJsb2NrUHVibGljUG9saWN5OiBmYWxzZSwgaWdub3JlUHVibGljQWNsczogZmFsc2UsIHJlc3RyaWN0UHVibGljQnVja2V0czogZmFsc2UgfVxuICAgIH0pO1xuXG4gICAgLy8gMy4gRGF0YWJhc2UgKFJEUyBNeVNRTClcbiAgICAvLyBUMy5NSUNSTyBpcyB0aGUgbW9kZXJuIEZyZWUgVGllciBzdGFuZGFyZFxuICAgIGNvbnN0IGRiID0gbmV3IHJkcy5EYXRhYmFzZUluc3RhbmNlKHRoaXMsICdTa2lsbFN3YXBEQicsIHtcbiAgICAgIGVuZ2luZTogcmRzLkRhdGFiYXNlSW5zdGFuY2VFbmdpbmUubXlzcWwoeyB2ZXJzaW9uOiByZHMuTXlzcWxFbmdpbmVWZXJzaW9uLlZFUl84XzAgfSksXG4gICAgICB2cGMsXG4gICAgICB2cGNTdWJuZXRzOiB7IHN1Ym5ldFR5cGU6IGVjMi5TdWJuZXRUeXBlLlBSSVZBVEVfV0lUSF9FR1JFU1MgfSwgLy8gREIgc3RheXMgcHJpdmF0ZSBmb3Igc2VjdXJpdHlcbiAgICAgIGluc3RhbmNlVHlwZTogZWMyLkluc3RhbmNlVHlwZS5vZihlYzIuSW5zdGFuY2VDbGFzcy5UMywgZWMyLkluc3RhbmNlU2l6ZS5NSUNSTyksXG4gICAgICBhbGxvY2F0ZWRTdG9yYWdlOiAyMCxcbiAgICAgIGRhdGFiYXNlTmFtZTogJ3NraWxsc3dhcCcsXG4gICAgICBjcmVkZW50aWFsczogcmRzLkNyZWRlbnRpYWxzLmZyb21HZW5lcmF0ZWRTZWNyZXQoJ2xhcmF2ZWwnKSxcbiAgICAgIG11bHRpQXo6IGZhbHNlLFxuICAgICAgZGVsZXRpb25Qcm90ZWN0aW9uOiBmYWxzZSxcbiAgICAgIHJlbW92YWxQb2xpY3k6IGNkay5SZW1vdmFsUG9saWN5LkRFU1RST1lcbiAgICB9KTtcblxuICAgIC8vIDQuIEVDMiBJbnN0YW5jZSAoQXBwIFNlcnZlcilcbiAgICBjb25zdCByb2xlID0gbmV3IGlhbS5Sb2xlKHRoaXMsICdBcHBSb2xlJywge1xuICAgICAgYXNzdW1lZEJ5OiBuZXcgaWFtLlNlcnZpY2VQcmluY2lwYWwoJ2VjMi5hbWF6b25hd3MuY29tJyksXG4gICAgICBtYW5hZ2VkUG9saWNpZXM6IFtcbiAgICAgICAgaWFtLk1hbmFnZWRQb2xpY3kuZnJvbUF3c01hbmFnZWRQb2xpY3lOYW1lKCdBbWF6b25TU01NYW5hZ2VkSW5zdGFuY2VDb3JlJyksIC8vIFJlcXVpcmVkIGZvciBTZXNzaW9uIE1hbmFnZXJcbiAgICAgICAgaWFtLk1hbmFnZWRQb2xpY3kuZnJvbUF3c01hbmFnZWRQb2xpY3lOYW1lKCdTZWNyZXRzTWFuYWdlclJlYWRXcml0ZScpLCAvLyBUbyByZWFkIERCIHBhc3N3b3JkXG4gICAgICAgIGlhbS5NYW5hZ2VkUG9saWN5LmZyb21Bd3NNYW5hZ2VkUG9saWN5TmFtZSgnQW1hem9uUzNGdWxsQWNjZXNzJykgLy8gVG8gc3luYyBhc3NldHNcbiAgICAgIF1cbiAgICB9KTtcblxuICAgIGNvbnN0IHVzZXJEYXRhID0gZWMyLlVzZXJEYXRhLmZvckxpbnV4KCk7XG4gICAgdXNlckRhdGEuYWRkQ29tbWFuZHMoXG4gICAgICAneXVtIHVwZGF0ZSAteScsXG4gICAgICAnYW1hem9uLWxpbnV4LWV4dHJhcyBpbnN0YWxsIC15IHBocDguMSBuZ2lueDEnLFxuICAgICAgJ3l1bSBpbnN0YWxsIC15IGdpdCBteXNxbCcsXG4gICAgICAnc3lzdGVtY3RsIGVuYWJsZSAtLW5vdyBuZ2lueCdcbiAgICApO1xuXG4gICAgY29uc3QgaW5zdGFuY2UgPSBuZXcgZWMyLkluc3RhbmNlKHRoaXMsICdBcHBJbnN0YW5jZScsIHtcbiAgICAgIHZwYyxcbiAgICAgIC8vIEZJWEVEOiBVc2luZyBQVUJMSUMgc3VibmV0IHNvIFNTTSB3b3JrcyB3aXRob3V0IE5BVCBHYXRld2F5XG4gICAgICB2cGNTdWJuZXRzOiB7IHN1Ym5ldFR5cGU6IGVjMi5TdWJuZXRUeXBlLlBVQkxJQyB9LFxuICAgICAgaW5zdGFuY2VUeXBlOiBlYzIuSW5zdGFuY2VUeXBlLm9mKGVjMi5JbnN0YW5jZUNsYXNzLlQzLCBlYzIuSW5zdGFuY2VTaXplLk1JQ1JPKSxcbiAgICAgIG1hY2hpbmVJbWFnZTogZWMyLk1hY2hpbmVJbWFnZS5sYXRlc3RBbWF6b25MaW51eDIoKSxcbiAgICAgIHJvbGU6IHJvbGUsXG4gICAgICB1c2VyRGF0YTogdXNlckRhdGEsXG4gICAgICAvLyBBbGxvdyBwdWJsaWMgSVAgc28gaXQgY2FuIHJlYWNoIHRoZSBpbnRlcm5ldFxuICAgICAgYXNzb2NpYXRlUHVibGljSXBBZGRyZXNzOiB0cnVlLCBcbiAgICB9KTtcbiAgICBcbiAgICAvLyBBbGxvdyBEQiBhY2Nlc3MgZnJvbSBFQzJcbiAgICBkYi5jb25uZWN0aW9ucy5hbGxvd0Zyb20oaW5zdGFuY2UsIGVjMi5Qb3J0LnRjcCgzMzA2KSk7XG5cbiAgICAvLyA1LiBMb2FkIEJhbGFuY2VyICYgQ2xvdWRGcm9udFxuICAgIGNvbnN0IGxiID0gbmV3IGVsYnYyLkFwcGxpY2F0aW9uTG9hZEJhbGFuY2VyKHRoaXMsICdMQicsIHtcbiAgICAgIHZwYyxcbiAgICAgIGludGVybmV0RmFjaW5nOiB0cnVlXG4gICAgfSk7XG4gICAgY29uc3QgbGlzdGVuZXIgPSBsYi5hZGRMaXN0ZW5lcignTGlzdGVuZXInLCB7IHBvcnQ6IDgwIH0pO1xuICAgIGxpc3RlbmVyLmFkZFRhcmdldHMoJ1RhcmdldCcsIHtcbiAgICAgIHBvcnQ6IDgwLFxuICAgICAgdGFyZ2V0czogW25ldyB0YXJnZXRzLkluc3RhbmNlVGFyZ2V0KGluc3RhbmNlKV0sXG4gICAgICBoZWFsdGhDaGVjazogeyBwYXRoOiAnL2hlYWx0aC1jaGVjay5waHAnIH1cbiAgICB9KTtcbiAgICBcbiAgICAvLyBBbGxvdyBBTEIgdG8gdGFsayB0byBFQzIgb24gcG9ydCA4MFxuICAgIGluc3RhbmNlLmNvbm5lY3Rpb25zLmFsbG93RnJvbShsYiwgZWMyLlBvcnQudGNwKDgwKSk7XG5cbiAgICBjb25zdCBkaXN0cmlidXRpb24gPSBuZXcgY2xvdWRmcm9udC5EaXN0cmlidXRpb24odGhpcywgJ1NraWxsU3dhcENGJywge1xuICAgICAgZGVmYXVsdEJlaGF2aW9yOiB7XG4gICAgICAgIG9yaWdpbjogbmV3IG9yaWdpbnMuTG9hZEJhbGFuY2VyVjJPcmlnaW4obGIpLFxuICAgICAgICB2aWV3ZXJQcm90b2NvbFBvbGljeTogY2xvdWRmcm9udC5WaWV3ZXJQcm90b2NvbFBvbGljeS5BTExPV19BTEwsXG4gICAgICAgIGFsbG93ZWRNZXRob2RzOiBjbG91ZGZyb250LkFsbG93ZWRNZXRob2RzLkFMTE9XX0FMTCxcbiAgICAgICAgY2FjaGVQb2xpY3k6IGNsb3VkZnJvbnQuQ2FjaGVQb2xpY3kuQ0FDSElOR19ESVNBQkxFRCxcbiAgICAgICAgb3JpZ2luUmVxdWVzdFBvbGljeTogY2xvdWRmcm9udC5PcmlnaW5SZXF1ZXN0UG9saWN5LkFMTF9WSUVXRVIsXG4gICAgICB9LFxuICAgIH0pO1xuXG4gICAgLy8gT3V0cHV0c1xuICAgIG5ldyBjZGsuQ2ZuT3V0cHV0KHRoaXMsICdBTEJFbmRwb2ludCcsIHsgdmFsdWU6IGBodHRwOi8vJHtsYi5sb2FkQmFsYW5jZXJEbnNOYW1lfWAgfSk7XG4gICAgbmV3IGNkay5DZm5PdXRwdXQodGhpcywgJ0Nsb3VkRnJvbnRFbmRwb2ludCcsIHsgdmFsdWU6IGBodHRwczovLyR7ZGlzdHJpYnV0aW9uLmRpc3RyaWJ1dGlvbkRvbWFpbk5hbWV9YCB9KTtcbiAgICBuZXcgY2RrLkNmbk91dHB1dCh0aGlzLCAnQnVja2V0TmFtZScsIHsgdmFsdWU6IGJ1Y2tldC5idWNrZXROYW1lIH0pO1xuICAgIG5ldyBjZGsuQ2ZuT3V0cHV0KHRoaXMsICdEQlNlY3JldEFybicsIHsgdmFsdWU6IGRiLnNlY3JldD8uc2VjcmV0QXJuIHx8ICcnIH0pO1xuICAgIG5ldyBjZGsuQ2ZuT3V0cHV0KHRoaXMsICdJbnN0YW5jZUlkJywgeyB2YWx1ZTogaW5zdGFuY2UuaW5zdGFuY2VJZCB9KTtcbiAgfVxufSJdfQ==