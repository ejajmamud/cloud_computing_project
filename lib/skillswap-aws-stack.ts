import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as rds from 'aws-cdk-lib/aws-rds';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as elbv2 from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import * as targets from 'aws-cdk-lib/aws-elasticloadbalancingv2-targets';
import * as cloudfront from 'aws-cdk-lib/aws-cloudfront';
import * as origins from 'aws-cdk-lib/aws-cloudfront-origins';

export class SkillswapAwsStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
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
    userData.addCommands(
      'yum update -y',
      'amazon-linux-extras install -y php8.1 nginx1',
      'yum install -y git mysql',
      'systemctl enable --now nginx'
    );

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