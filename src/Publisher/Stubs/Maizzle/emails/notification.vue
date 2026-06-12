<script setup>
  defineConfig({
    title: 'New Notification',
    message: 'Something interesting just happened in your account.',
    actionUrl: 'https://example.com/dashboard',
    actionText: 'View Dashboard'
  })
</script>

<template>
  <Layout>
    <Container class="max-w-xl bg-white p-8 rounded-lg shadow-sm border border-gray-100 mt-10">
      <div class="mb-8">
        <Img src="logo.png" class="h-8" alt="Logo" />
      </div>
      
      <Heading class="text-xl font-bold text-gray-800 mb-4">
        {{ title }}
      </Heading>
      
      <Text class="text-gray-600 leading-relaxed mb-8">
        {{ message }}
      </Text>
      
      <Button :href="actionUrl" class="bg-gray-800 text-white font-bold py-3 px-6 rounded hover:bg-black">
        {{ actionText }}
      </Button>
    </Container>
    
    <Text class="text-center text-xs text-gray-400 mt-6">
      You are receiving this because you signed up for notifications.<br>
      <a href="#" class="text-blue-500 underline">Unsubscribe</a>
    </Text>
  </Layout>
</template>
