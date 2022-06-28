def subject = "${env.JOB_NAME} - Build #${env.BUILD_NUMBER}"
def content = '${JELLY_SCRIPT,template="html"}'
def stage_tag = 'staging'
def project_name = 'innovation-catalogue-connect'
def deployment_instance = 'staging.innovation.api.scio.services'

pipeline {
    agent any

    stages {
        stage('Fetching repository from BitBucket') {
            steps {
                echo 'Fetching repository from BitBucket'

                script {
                    checkout scm
                    sh 'git rev-parse --short HEAD > .git/commit-id'
                    commit_id = readFile('.git/commit-id').trim()
                }
            }
        }

        stage('Building & pushing docker images to DockerHub') {
            steps {
                echo 'Building project'

                script {
                    echo 'Downloading from S3 bucket'
                    withAWS(region: 'us-east-2', credentials:'jenkins-aws') {
                        s3Download(file: '.env', bucket: 'scio-project-envs', path: "laravel/${project_name}/${stage_tag}.env", force:true)
                    }

                    docker.withRegistry('https://index.docker.io/v1/', 'DockerHub') {
                        docker.build("sciohub/${project_name}:${stage_tag}", '.').push()
                    }
                    
                    docker.withRegistry('https://index.docker.io/v1/', 'DockerHub') {
                        docker.build("sciohub/${project_name}-nginx:${stage_tag}", '-f nginx/Dockerfile .').push()
                    }
                }
            }
        }

        stage('Deployment stage') {
            steps {
                echo "Deploying to ${stage_name} environment"

                script {
                    sshagent(credentials: ['jenkins-ssh-key']) {
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} sudo mkdir -p /var/lib/${project_name}-${stage_tag}"
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} sudo chown -R scio:scio /var/lib/${project_name}-${stage_tag}"
                        sh "scp -P 1412 -o StrictHostKeyChecking=no docker-compose.${stage_tag}.yml scio@${deployment_instance}:/var/lib/${project_name}-${stage_tag}"                        
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker rm -f laravel-app laravel-nginx"
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker rmi -f sciohub/${project_name}:${stage_tag}"
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker-compose -f /var/lib/${project_name}-${stage_tag}/docker-compose.${stage_tag}.yml up -d"
                        sh "ssh -p1412 -o StrictHostKeyChecking=no scio@${deployment_instance} docker exec laravel-app chown -R www-data:www-data ."                    
                    }
                }
            }
        }
    }

    // Cleaning Jenkins workspace
    post {
        always {
            sh 'docker image prune -a -f' // remove built images
            emailext(body: content, mimeType: 'text/html',
        replyTo: '$DEFAULT_REPLYTO', subject: subject,
        to: 'dev@scio.systems', attachLog: true )
            cleanWs()
        }
    }
}
