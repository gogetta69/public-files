# Use CentOS 6 as the base image
FROM centos:6

# Install development tools and dependencies
RUN yum -y update && \
    yum groupinstall -y "Development Tools" && \
    yum install -y centos-release-scl && \
    yum install -y wget && \
    yum clean all

# Install Developer Toolset (GCC 7)
RUN yum install -y devtoolset-7-gcc devtoolset-7-gcc-c++ devtoolset-7-binutils && \
    yum clean all

# Download and install Node.js
RUN wget https://nodejs.org/dist/v20.13.1/node-v20.13.1-linux-x64.tar.xz && \
    tar -xJf node-v20.13.1-linux-x64.tar.xz -C /usr/local --strip-components=1 && \
    rm node-v20.13.1-linux-x64.tar.xz && \
    yum clean all

# Enable the Developer Toolset (GCC 7)
RUN echo "source /opt/rh/devtoolset-7/enable" >> /etc/profile

# Set Node.js environment variables
ENV PATH="/usr/local/bin:${PATH}"

# Install Nexe globally
RUN npm install -g nexe

# Set the working directory
WORKDIR /app

# Copy the application files
COPY . .

# Build the application using Nexe
CMD ["nexe", "thetvapp-win64.js", "-t", "linux-x64-20.13.1", "-o", "thetvapp-linux", "--build"]
