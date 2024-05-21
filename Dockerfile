# Use CentOS 6 as the base image
FROM centos:6

# Install development tools and dependencies
RUN yum groupinstall -y "Development Tools" && \
    yum install -y centos-release-scl && \
    yum install -y devtoolset-7-gcc devtoolset-7-gcc-c++ devtoolset-7-binutils && \
    yum install -y wget && \
    wget https://nodejs.org/dist/v20.13.1/node-v20.13.1-linux-x64.tar.xz && \
    tar -xJf node-v20.13.1-linux-x64.tar.xz -C /usr/local --strip-components=1 && \
    yum clean all

# Enable the Developer Toolset (GCC 7)
RUN scl enable devtoolset-7 bash

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
