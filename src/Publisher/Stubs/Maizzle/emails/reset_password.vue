<script setup>
  defineConfig({
    user: 'friend',
    resetUrl: 'https://example.com/reset',
  })
</script>

<template>
  <Layout>
    <Container class="max-w-xl bg-white p-8 rounded-lg shadow-sm border border-gray-100 mt-10">
      <div class="text-center mb-8">
        <Img src="logo.png" class="mx-auto h-12" alt="Logo" />
      </div>
      
      <Heading class="text-2xl font-bold text-gray-800 mb-4">
        Reset Your Password
      </Heading>
      
      <Text class="text-gray-600 leading-relaxed mb-6">
        Hello {{ user }},<br><br>
        We received a request to reset the password for your account. Click the button below to choose a new one.
      </Text>
      
      <Button :href="resetUrl" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-md hover:bg-blue-700 transition duration-200">
        Reset Password
      </Button>
      
      <Text class="text-sm text-gray-500 mt-8 border-t border-gray-100 pt-6">
        If you didn't request a password reset, you can safely ignore this email.
      </Text>
    </Container>
    
    <Text class="text-center text-xs text-gray-400 mt-6">
      © {{ new Date().getFullYear() }} Jengo Framework. All rights reserved.
    </Text>
  </Layout>
</template>
